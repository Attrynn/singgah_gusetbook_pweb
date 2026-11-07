<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates bounded image derivatives for approved media.
 *
 * Pending media is never published, so no public derivative is created until
 * an asset has been approved and moved to the public disk.
 */
class MediaDerivativeService
{
    /**
     * @return array{thumbnail_path:?string,medium_path:?string}
     */
    public function generate(MediaAsset $asset): array
    {
        $publicDisk = config('media.public_disk', 'public');

        if ($asset->storageDisk() !== $publicDisk || ! $asset->isApproved()) {
            return ['thumbnail_path' => $asset->thumbnail_path, 'medium_path' => $asset->medium_path];
        }

        if (! function_exists('imagecreatefromstring')) {
            return ['thumbnail_path' => $asset->thumbnail_path, 'medium_path' => $asset->medium_path];
        }

        try {
            $contents = Storage::disk($publicDisk)->get($asset->path);
            $image = @imagecreatefromstring($contents);

            if ($image === false) {
                return ['thumbnail_path' => $asset->thumbnail_path, 'medium_path' => $asset->medium_path];
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $base = pathinfo($asset->path, PATHINFO_FILENAME);
            $directory = dirname($asset->path);

            $updates = [
                'thumbnail_path' => $asset->thumbnail_path,
                'medium_path' => $asset->medium_path,
            ];

            foreach (['thumb' => 480, 'medium' => 1280] as $name => $max) {
                $target = "{$directory}/{$name}/{$base}.jpg";

                if (max($width, $height) <= $max) {
                    // Keep the original for small images; it is already bounded.
                    $updates[$name === 'thumb' ? 'thumbnail_path' : 'medium_path'] = $asset->path;

                    continue;
                }

                $ratio = min($max / max($width, 1), $max / max($height, 1));
                $newWidth = max(1, (int) round($width * $ratio));
                $newHeight = max(1, (int) round($height * $ratio));

                $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC)
                    ?: imagescale($image, $newWidth, $newHeight, IMG_BILINEAR_FIXED);

                if ($resized === false) {
                    continue;
                }

                ob_start();
                imagejpeg($resized, null, 82);
                $data = (string) ob_get_clean();
                imagedestroy($resized);

                Storage::disk($publicDisk)->put($target, $data);
                $updates[$name === 'thumb' ? 'thumbnail_path' : 'medium_path'] = $target;
            }

            imagedestroy($image);

            $asset->forceFill($updates)->save();

            return $updates;
        } catch (Throwable $exception) {
            report($exception);

            return ['thumbnail_path' => $asset->thumbnail_path, 'medium_path' => $asset->medium_path];
        }
    }
}
