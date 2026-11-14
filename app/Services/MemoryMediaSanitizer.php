<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Security boundary for guest-contributed images.
 *
 * Untrusted uploads are decoded and re-encoded into clean bytes so no
 * metadata, scripts or polyglot payloads survive into storage. Client
 * supplied names, MIME types and extensions are never trusted.
 */
class MemoryMediaSanitizer
{
    /**
     * Re-encode an uploaded image into metadata-free bytes.
     *
     * @return array{bytes:string,mime:string,extension:string,width:int,height:int,checksum:string}
     */
    public function sanitize(UploadedFile $file): array
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->fail('Pemrosesan gambar tidak tersedia pada server ini.');
        }

        $raw = $file->get();

        if ($raw === false || $raw === '') {
            $this->fail('Berkas yang diunggah kosong atau rusak.');
        }

        $header = getimagesizefromstring($raw);

        if ($header === false) {
            $this->fail('Berkas bukan gambar yang valid.');
        }

        $format = match ($header[2] ?? null) {
            IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
            IMAGETYPE_PNG => ['image/png', 'png'],
            IMAGETYPE_WEBP => ['image/webp', 'webp'],
            default => null,
        };

        if ($format === null) {
            $this->fail('Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.');
        }

        [$mime, $extension] = $format;
        $width = (int) $header[0];
        $height = (int) $header[1];

        if ($width * $height > (int) config('media.max_pixels', 36_000_000)) {
            $this->fail('Dimensi gambar melebihi batas yang diizinkan.');
        }

        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            $this->fail('Gagal memproses gambar. Silakan coba lagi.');
        }

        try {
            if ($header[2] === IMAGETYPE_PNG) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            ob_start();

            match ($header[2]) {
                IMAGETYPE_JPEG => imagejpeg($image, null, 90),
                IMAGETYPE_PNG => imagepng($image, null, 6),
                IMAGETYPE_WEBP => imagewebp($image, null, 90),
            };

            $bytes = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if ($bytes === '') {
            $this->fail('Gagal mengode ulang gambar. Silakan coba lagi.');
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'checksum' => hash('sha256', $bytes),
        ];
    }

    /**
     * Throw a validation failure carrying an Indonesian message on the
     * `files` key so the upload form can surface it directly.
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['files' => $message]);
    }
}
