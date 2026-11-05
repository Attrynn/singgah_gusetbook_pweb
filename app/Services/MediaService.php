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

class MediaService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function storeGuestUpload(Guest $guest, UploadedFile $file, ?string $caption = null): MediaAsset
    {
        $guest->loadMissing('event');
        $event = $guest->event;
        $path = $file->store("events/{$event->id}/guest-uploads", 'public');

        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'path' => $path,
            'thumbnail_path' => null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'image/jpeg',
            'size_bytes' => $file->getSize(),
            'caption' => $caption,
            'status' => MediaStatus::Pending,
        ]);

        $this->audit->guestAction($event, $guest, 'media.uploaded', "Foto diunggah oleh {$guest->name}", ['asset_id' => $asset->id]);

        return $asset;
    }

    public function storeEventUpload(Event $event, UploadedFile $file, User $actor, ?MediaAlbum $album = null, bool $approved = true): MediaAsset
    {
        $path = $file->store("events/{$event->id}/gallery", 'public');

        $asset = MediaAsset::create([
            'event_id' => $event->id,
            'media_album_id' => $album?->id,
            'uploaded_by' => $actor->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'image/jpeg',
            'size_bytes' => $file->getSize(),
            'caption' => $file->getClientOriginalName(),
            'status' => $approved ? MediaStatus::Approved : MediaStatus::Pending,
            'reviewed_by' => $approved ? $actor->id : null,
            'reviewed_at' => $approved ? now() : null,
        ]);

        if ($album) {
            $album->items()->firstOrCreate(['media_asset_id' => $asset->id], ['sort_order' => $album->items()->count()]);
        }

        $this->audit->record('media.uploaded', "Foto ditambahkan ke galeri {$event->name}", $event, $actor, $asset);

        return $asset;
    }

    public function moderate(MediaAsset $asset, string $action, User $actor): MediaAsset
    {
        $status = match ($action) {
            'approve' => MediaStatus::Approved,
            'reject' => MediaStatus::Rejected,
            default => MediaStatus::Pending,
        };

        $asset->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->record(
            'media.'.$action,
            "Foto {$asset->original_name} di-{$action}",
            $asset->event,
            $actor,
            $asset,
            ['status' => $status->value]
        );

        return $asset->fresh();
    }

    public function toggleFeatured(MediaAsset $asset, User $actor): MediaAsset
    {
        $asset->update(['is_featured' => ! $asset->is_featured]);
        $this->audit->record('media.featured', "Foto {$asset->original_name} diubah status unggulan", $asset->event, $actor, $asset);

        return $asset->fresh();
    }

    public function delete(MediaAsset $asset, User $actor): void
    {
        DB::transaction(function () use ($asset, $actor) {
            Storage::disk('public')->delete(array_filter([$asset->path, $asset->thumbnail_path]));
            $this->audit->record('media.deleted', "Foto dihapus: {$asset->original_name}", $asset->event, $actor, $asset);
            $asset->delete();
        });
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
        $this->audit->record('album.deleted', "Album dihapus: {$album->name}", $album->event, $actor, $album);
        $album->delete();
    }

    public function attachToAlbum(MediaAlbum $album, MediaAsset $asset): void
    {
        if ($asset->event_id !== $album->event_id) {
            return;
        }

        $album->items()->firstOrCreate(
            ['media_asset_id' => $asset->id],
            ['sort_order' => $album->items()->count()]
        );
        $asset->update(['media_album_id' => $album->id]);
    }
}
