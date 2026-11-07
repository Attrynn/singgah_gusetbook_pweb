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
use RuntimeException;
use Throwable;

class MediaService
{
    public function __construct(
        protected AuditService $audit,
        protected MediaDerivativeService $derivatives,
    ) {}

    /**
     * Guest uploads are stored on the private disk. They are never publicly
     * reachable, whether pending, approved or rejected.
     */
    public function storeGuestUpload(Guest $guest, UploadedFile $file, ?string $caption = null): MediaAsset
    {
        $guest->loadMissing('event');
        $event = $guest->event;

        $disk = $this->privateDisk();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $stagedPath = $file->storeAs(
            "events/{$event->id}/pending",
            Str::uuid()->toString().'.'.$extension,
            $disk
        );

        if ($stagedPath === false) {
            throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }

        try {
            $asset = DB::transaction(fn (): MediaAsset => MediaAsset::create([
                'event_id' => $event->id,
                'disk' => $disk,
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
            Storage::disk($disk)->delete($stagedPath);

            throw $e;
        }

        $this->audit->guestAction($event, $guest, 'media.uploaded', "Foto diunggah oleh {$guest->name}", ['asset_id' => $asset->id]);

        return $asset;
    }

    /**
     * Superadmin uploads are stored privately as well; staff see them through
     * the same signed delivery endpoint as guests.
     */
    public function storeEventUpload(Event $event, UploadedFile $file, User $actor, ?MediaAlbum $album = null, bool $approved = true): MediaAsset
    {
        if ($album && $album->event_id !== $event->id) {
            throw ValidationException::withMessages([
                'media_album_id' => 'Album yang dipilih tidak termasuk dalam acara ini.',
            ]);
        }

        $disk = $this->privateDisk();
        $path = $file->store("events/{$event->id}/gallery", $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['files' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }

        try {
            $asset = DB::transaction(fn (): MediaAsset => MediaAsset::create([
                'event_id' => $event->id,
                'disk' => $disk,
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
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        $this->audit->record('media.uploaded', "Foto ditambahkan ke galeri {$event->name}", $event, $actor, $asset);

        $this->derivatives->generate($asset);

        return $asset->fresh();
    }

    /**
     * Approval changes the access policy only; the object stays private.
     * Rejection and demotion revoke access immediately. Physical cleanup is
     * attempted after the access state is committed and is replayed by the
     * purge command if storage deletion fails.
     */
    public function moderate(MediaAsset $asset, string $action, User $actor): MediaAsset
    {
        $status = match ($action) {
            'approve' => MediaStatus::Approved,
            'reject' => MediaStatus::Rejected,
            default => MediaStatus::Pending,
        };

        $disk = $asset->storageDisk();

        if ($status === MediaStatus::Approved && ! Storage::disk($disk)->exists($asset->path)) {
            throw ValidationException::withMessages([
                'asset' => 'Berkas foto tidak ditemukan pada penyimpanan. Persetujuan dibatalkan.',
            ]);
        }

        DB::transaction(function () use ($asset, $status, $actor, $action): void {
            $asset->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->recordCritical(
                'media.'.$action,
                "Foto {$asset->original_name} di-{$action}",
                $asset->event,
                $actor,
                $asset,
                ['status' => $status->value]
            );
        });

        $fresh = $asset->fresh();

        if ($status === MediaStatus::Approved) {
            $this->derivatives->generate($fresh);
        } else {
            $this->withdrawDerivatives($fresh);

            if ($status === MediaStatus::Rejected) {
                $this->attemptCleanup($fresh, [$fresh->path, $fresh->thumbnail_path, $fresh->medium_path]);
            }
        }

        return $fresh->fresh();
    }

    public function toggleFeatured(MediaAsset $asset, User $actor): MediaAsset
    {
        $asset->update(['is_featured' => ! $asset->is_featured]);
        $this->audit->record('media.featured', "Foto {$asset->original_name} diubah status unggulan", $asset->event, $actor, $asset);

        return $asset->fresh();
    }

    /**
     * Deletion is physical-then-database: files are removed first so a storage
     * failure aborts the operation instead of leaving an untracked object.
     */
    public function delete(MediaAsset $asset, User $actor): void
    {
        $paths = array_values(array_unique(array_filter([
            $asset->path, $asset->thumbnail_path, $asset->medium_path,
        ])));

        $this->deleteFiles($asset, $paths);

        DB::transaction(function () use ($asset, $actor): void {
            MediaAlbum::where('cover_media_asset_id', $asset->id)->update(['cover_media_asset_id' => null]);

            $this->audit->record('media.deleted', "Foto dihapus: {$asset->original_name}", $asset->event, $actor, $asset);
            $asset->delete();
        });
    }

    /**
     * @param  array<int,string|null>  $paths
     */
    protected function deleteFiles(MediaAsset $asset, array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return;
        }

        foreach ($this->storageDisks($asset) as $disk) {
            if (! Storage::disk($disk)->delete($paths)) {
                throw new RuntimeException("Gagal menghapus berkas media pada disk {$disk}.");
            }
        }
    }

    /**
     * Best-effort cleanup used after an access-state change has committed.
     * Failures are reported and remain recoverable through the purge command.
     *
     * @param  array<int,string|null>  $paths
     */
    protected function attemptCleanup(MediaAsset $asset, array $paths): void
    {
        try {
            $this->deleteFiles($asset, $paths);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function withdrawDerivatives(MediaAsset $asset): void
    {
        $this->attemptCleanup($asset, [$asset->thumbnail_path, $asset->medium_path]);

        if ($asset->thumbnail_path || $asset->medium_path) {
            $asset->forceFill(['thumbnail_path' => null, 'medium_path' => null])->save();
        }
    }

    /**
     * Every disk an asset's objects may exist on, including legacy public
     * copies left behind by the pre-private storage model.
     *
     * @return array<int,string>
     */
    protected function storageDisks(MediaAsset $asset): array
    {
        return array_values(array_unique(array_filter([
            $asset->storageDisk(),
            $this->privateDisk(),
            config('media.public_disk', 'public'),
        ])));
    }

    protected function privateDisk(): string
    {
        return (string) config('media.private_disk', 'local');
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

    /**
     * Deleting an album detaches its media instead of cascading, clears the
     * cover reference and records the change durably.
     */
    public function deleteAlbum(MediaAlbum $album, User $actor): void
    {
        DB::transaction(function () use ($album, $actor): void {
            MediaAsset::where('media_album_id', $album->id)->update(['media_album_id' => null]);

            $album->forceFill(['cover_media_asset_id' => null])->save();

            $this->audit->recordCritical('album.deleted', "Album dihapus: {$album->name}", $album->event, $actor, $album);

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
