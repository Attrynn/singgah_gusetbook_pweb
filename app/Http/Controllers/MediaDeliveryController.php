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
 */
class MediaDeliveryController extends Controller
{
    public function __invoke(Request $request, MediaAsset $asset): StreamedResponse
    {
        abort_if($asset->trashed(), 404);
        abort_unless($asset->isApproved(), 404);

        $event = $asset->event;

        abort_unless($event && ! $event->trashed(), 404);
        abort_unless($event->status->isPublic(), 404);
        abort_unless((bool) $event->gallery_enabled, 404);

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
}
