<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Generates bounded image derivatives for approved media.
 *
 * Derivatives live on the same private disk as the original. Writes are
 * checked: a rendition that cannot be persisted is never recorded in the
 * database, so the UI cannot claim a successful publication that did not
 * happen.
 */
class MediaDerivativeService
{
    /**
     * @return array{thumbnail_path:?string,medium_path:?string}
     */
    public function generate(MediaAsset $asset): array
    {
        $current = ['thumbnail_path' => $asset->thumbnail_path, 'medium_path' => $asset->medium_path];

        if (! $asset->isApproved()) {
            return $current;
        }

        $disk = $asset->storageDisk();

        if (! function_exists('imagecreatefromstring')) {
            return $current;
        }

        try {
            if (! Storage::disk($disk)->exists($asset->path)) {
                return $current;
            }

            $contents = Storage::disk($disk)->get($asset->path);

            // Pre-decode guard: inspect the header (format + dimensions)
            // before allocating a GD canvas. A file that claims an
            // oversized or unsupported canvas is skipped without ever
            // being decoded, so it cannot exhaust process memory.
            $header = getimagesizefromstring($contents);
            if ($header === false || ! in_array($header['mime'] ?? null, config('media.derivative_mimes', ['image/jpeg', 'image/png', 'image/webp']), true)) {
                return $current;
            }

            $maxPixels = (int) config('media.max_pixels', 36_000_000);
            if (($header[0] * $header[1]) > $maxPixels) {
                return $current;
            }

            $image = @imagecreatefromstring($contents);

            if ($image === false) {
                return $current;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            $base = pathinfo($asset->path, PATHINFO_FILENAME);
            $directory = dirname($asset->path);

            $updates = $current;

            foreach (['thumb' => 480, 'medium' => 1280] as $name => $max) {
                $target = "{$directory}/{$name}/{$base}.jpg";

                if (max($width, $height) <= $max) {
                    $newWidth = $width;
                    $newHeight = $height;
                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                    if ($resized !== false) {
                        imagecopy($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight);
                    }
                } else {
                    $ratio = min($max / max($width, 1), $max / max($height, 1));
                    $newWidth = max(1, (int) round($width * $ratio));
                    $newHeight = max(1, (int) round($height * $ratio));

                    $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC)
                        ?: imagescale($image, $newWidth, $newHeight, IMG_BILINEAR_FIXED);
                }

                if ($resized === false) {
                    continue;
                }

                ob_start();
                imagejpeg($resized, null, 82);
                $data = (string) ob_get_clean();
                imagedestroy($resized);

                if (! Storage::disk($disk)->put($target, $data)) {
                    report(new RuntimeException("Failed to write media derivative {$target} on disk {$disk}."));

                    continue;
                }

                $updates[$name === 'thumb' ? 'thumbnail_path' : 'medium_path'] = $target;
            }

            imagedestroy($image);

            $asset->forceFill($updates)->save();

            return $updates;
        } catch (Throwable $exception) {
            report($exception);

            return $current;
        }
    }
}
