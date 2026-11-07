<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MediaService
{
    public function __construct(
        protected AuditService $audit,
        protected MediaDerivativeService $derivatives,
    ) {}

    /**
     * Guest uploads are staged on a private disk until a moderator approves
     * them. Nothing is publicly reachable before approval.
     */
    public function storeGuestUpload(Guest $guest, UploadedFile $file, ?string $caption = null): MediaAsset
    {
        $guest->loadMissing('event');
        $event = $guest->event;

        $stagingDisk = config('media.staging_disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $stagedPath = $file->storeAs(
            "events/{$event->id}/pending",
            Str::uuid()->toString().'.'.$extension,
            $stagingDisk
        );

        if ($stagedPath === false) {
            throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }

        try {
            $asset = DB::transaction(fn (): MediaAsset => MediaAsset::create([
                'event_id' => $event->id,
                'disk' => $stagingDisk,
                'guest_id' => $guest->id,
                'path' => $stagedPath,
                'thumbnail_path' => null,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                'size_bytes' => $file->getSize() ?: 0,
                'caption' => $caption,
                'status' => MediaStatus::Pending,
            ]));
        } catch (Throwable $e) {
            Storage::disk($stagingDisk)->delete($stagedPath);

            throw $e;
        }

        $this->audit->guestAction($event, $guest, 'media.uploaded', "Foto diunggah oleh {$guest->name}", ['asset_id' => $asset->id]);

        return $asset;
    }

    /**
     * Superadmin uploads go straight to the public disk.
     */
    public function storeEventUpload(Event $event, UploadedFile $file, User $actor, ?MediaAlbum $album = null, bool $approved = true): MediaAsset
    {
        if ($album && $album->event_id !== $event->id) {
            throw ValidationException::withMessages([
                'media_album_id' => 'Album yang dipilih tidak termasuk dalam acara ini.',
            ]);
        }

        $publicDisk = config('media.public_disk', 'public');
        $path = $file->store("events/{$event->id}/gallery", $publicDisk);

        if ($path === false) {
            throw ValidationException::withMessages(['files' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }

        try {
            $asset = DB::transaction(fn (): MediaAsset => MediaAsset::create([
                'event_id' => $event->id,
                'disk' => $publicDisk,
                'media_album_id' => $album?->id,
                'uploaded_by' => $actor->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'image/jpeg',
                'size_bytes' => $file->getSize() ?: 0,
                'caption' => $file->getClientOriginalName(),
                'sort_order' => $album ? ($album->assets()->max('sort_order') + 1) : 0,
                'status' => $approved ? MediaStatus::Approved : MediaStatus::Pending,
                'reviewed_by' => $approved ? $actor->id : null,
                'reviewed_at' => $approved ? now() : null,
            ]));
        } catch (Throwable $e) {
            Storage::disk($publicDisk)->delete($path);

            throw $e;
        }

        $this->audit->record('media.uploaded', "Foto ditambahkan ke galeri {$event->name}", $event, $actor, $asset);

        $this->derivatives->generate($asset);

        return $asset->fresh();
    }

    /**
     * Approving a staged guest upload moves it from the private staging disk
     * to the public disk. Rejecting deletes the staged file.
     */
    public function moderate(MediaAsset $asset, string $action, User $actor): MediaAsset
    {
        $status = match ($action) {
            'approve' => MediaStatus::Approved,
            'reject' => MediaStatus::Rejected,
            default => MediaStatus::Pending,
        };

        $originalDisk = $asset->storageDisk();
        $originalPath = $asset->path;
        $published = null;

        if ($status === MediaStatus::Approved && ! $asset->isPublished()) {
            $published = $this->publishStagedFile($asset);
        }

        try {
            DB::transaction(function () use ($asset, $status, $actor, $published): void {
                $asset->update([
                    'status' => $status,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    ...($published ?? []),
                ]);
            });
        } catch (Throwable $e) {
            if ($published) {
                Storage::disk($published['disk'])->delete($published['path']);
            }

            throw $e;
        }

        if ($status === MediaStatus::Rejected) {
            $this->deleteFiles($asset->fresh(), [$originalDisk], [$originalPath]);
        }

        if ($status === MediaStatus::Approved) {
            $this->derivatives->generate($asset->fresh());
        }

        $this->audit->recordCritical(
            'media.'.$action,
            "Foto {$asset->original_name} di-{$action}",
            $asset->event,
            $actor,
            $asset,
            ['status' => $status->value]
        );

        return $asset->fresh();
    }

    /**
     * Copy the staged file to the public disk and return the new attributes.
     *
     * @return array{disk: string, path: string}
     */
    protected function publishStagedFile(MediaAsset $asset): array
    {
        $publicDisk = config('media.public_disk', 'public');
        $sourceDisk = $asset->storageDisk();

        if (! Storage::disk($sourceDisk)->exists($asset->path)) {
            throw ValidationException::withMessages([
                'asset' => 'Berkas foto tidak ditemukan pada penyimpanan sementara.',
            ]);
        }

        $targetPath = "events/{$asset->event_id}/gallery/".basename($asset->path);

        if (! Storage::disk($publicDisk)->exists($targetPath)) {
            $stream = Storage::disk($sourceDisk)->readStream($asset->path);

            if ($stream === null) {
                throw ValidationException::withMessages([
                    'asset' => 'Berkas foto tidak dapat dibaca dari penyimpanan sementara.',
                ]);
            }

            Storage::disk($publicDisk)->writeStream($targetPath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return ['disk' => $publicDisk, 'path' => $targetPath];
    }

    public function toggleFeatured(MediaAsset $asset, User $actor): MediaAsset
    {
        $asset->update(['is_featured' => ! $asset->is_featured]);
        $this->audit->record('media.featured', "Foto {$asset->original_name} diubah status unggulan", $asset->event, $actor, $asset);

        return $asset->fresh();
    }

    public function delete(MediaAsset $asset, User $actor): void
    {
        $disk = $asset->storageDisk();
        $paths = array_filter([$asset->path, $asset->thumbnail_path]);

        DB::transaction(function () use ($asset, $actor, $disk, $paths): void {
            $this->audit->record('media.deleted', "Foto dihapus: {$asset->original_name}", $asset->event, $actor, $asset);
            $asset->delete();

            $this->deleteFiles($asset, [$disk], $paths);
        });
    }

    /**
     * @param  array<int,string>  $disks
     * @param  array<int,string>  $paths
     */
    protected function deleteFiles(MediaAsset $asset, array $disks, array $paths): void
    {
        foreach (array_unique($disks) as $disk) {
            Storage::disk($disk)->delete($paths);
        }
    }

    public function createAlbum(Event $event, array $data, User $actor): MediaAlbum
    {
        $album = MediaAlbum::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('album.created', "Album dibuat: {$album->name}", $event, $actor, $album);

        return $album;
    }

    public function deleteAlbum(MediaAlbum $album, User $actor): void
    {
        DB::transaction(function () use ($album, $actor): void {
            MediaAsset::where('media_album_id', $album->id)->update(['media_album_id' => null]);
            $this->audit->record('album.deleted', "Album dihapus: {$album->name}", $album->event, $actor, $album);
            $album->delete();
        });
    }

    public function attachToAlbum(MediaAlbum $album, MediaAsset $asset): void
    {
        if ($asset->event_id !== $album->event_id) {
            throw ValidationException::withMessages([
                'media_asset_id' => 'Foto dan album berasal dari acara yang berbeda.',
            ]);
        }

        $asset->update([
            'media_album_id' => $album->id,
            'sort_order' => ($album->assets()->max('sort_order') ?? -1) + 1,
        ]);
    }
}
