<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorised delivery endpoint for private media.
 *
 * Requests must carry a valid signature and the asset must still satisfy the
 * event's access policy at request time. Revoking approval, archiving or
 * deleting an event immediately denies access even for an already issued URL.
 *
 * The delivery context is part of the signed URL, so a caller cannot upgrade
 * a public link to owner access: `public` enforces the event's gallery
 * policy, while `owner` additionally covers archived and completed events
 * for the couple.
 */
class MediaDeliveryController extends Controller
{
    public function __invoke(Request $request, MediaAsset $asset): StreamedResponse
    {
        $context = (string) $request->query('context', 'public');

        abort_unless(in_array($context, ['public', 'owner'], true), 404);

        $this->assertDeliverable($asset, $context);

        $variant = (string) $request->query('variant', 'original');

        $path = match ($variant) {
            'thumb' => $asset->thumbnail_path ?: $asset->path,
            'medium' => $asset->medium_path ?: $asset->path,
            default => $asset->path,
        };

        $disk = $asset->storageDisk();

        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, $asset->original_name, [
            'Cache-Control' => 'private, max-age=300, no-transform',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * Enforce the access policy for the delivery context.
     *
     * Public delivery keeps the event's gallery policy: approved, unhidden,
     * publicly visible event with the gallery enabled. Owner delivery only
     * requires the asset and event to exist, so the couple can preview
     * submissions awaiting moderation and keep access to archived or
     * completed events. Any failure aborts with 404.
     */
    private function assertDeliverable(MediaAsset $asset, string $context): void
    {
        abort_if($asset->trashed(), 404);

        $event = $asset->event;

        abort_unless($event && ! $event->trashed(), 404);

        if ($context === 'public') {
            abort_unless($asset->isApproved(), 404);
            // Hidden media stays available to the couple so they can review
            // and unhide it, but must never reach the public gallery.
            abort_if($asset->isHidden(), 404);
            abort_unless($event->status->isPublic(), 404);
            abort_unless((bool) $event->gallery_enabled, 404);
        }
    }
}
