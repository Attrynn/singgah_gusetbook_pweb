<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrService
{
    public function svg(string $payload, int $size = 320): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($payload);
    }

    public function pngBinary(string $payload, int $size = 480, ?string $caption = null): string
    {
        $matrix = Encoder::encode($payload, ErrorCorrectionLevel::H())->getMatrix();
        $matrixWidth = $matrix->getWidth();
        $margin = 4;
        $modules = $matrixWidth + ($margin * 2);
        $scale = max(1, (int) floor($size / $modules));
        $imgSize = $modules * $scale;

        $img = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 37, 52, 71); // #253447 ink
        imagefill($img, 0, 0, $white);

        for ($y = 0; $y < $matrixWidth; $y++) {
            for ($x = 0; $x < $matrixWidth; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle(
                        $img,
                        ($x + $margin) * $scale,
                        ($y + $margin) * $scale,
                        ($x + $margin + 1) * $scale - 1,
                        ($y + $margin + 1) * $scale - 1,
                        $black
                    );
                }
            }
        }

        if ($caption !== null && $caption !== '') {
            $img = $this->appendCaption($img, $caption);
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        return $png;
    }

    public function pngDataUri(string $payload, int $size = 480, ?string $caption = null): string
    {
        return 'data:image/png;base64,'.base64_encode($this->pngBinary($payload, $size, $caption));
    }

    protected function appendCaption(\GdImage $image, string $caption): \GdImage
    {
        $width = imagesx($image);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($caption);
        $textHeight = imagefontheight($font);

        $text = imagecreatetruecolor($textWidth, $textHeight);
        $white = imagecolorallocate($text, 255, 255, 255);
        $ink = imagecolorallocate($text, 37, 52, 71);
        imagefill($text, 0, 0, $white);
        imagestring($text, $font, 0, 0, $caption, $ink);

        $scale = max(1, (int) floor(($width * 0.55) / max(1, $textWidth)));
        $scaledWidth = $textWidth * $scale;
        $scaledHeight = $textHeight * $scale;
        $gap = (int) round($width * 0.04);

        $canvas = imagecreatetruecolor($width, imagesy($image) + $gap + $scaledHeight);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, imagesy($image));
        imagecopyresampled(
            $canvas,
            $text,
            (int) (($width - $scaledWidth) / 2),
            imagesy($image) + $gap,
            0,
            0,
            $scaledWidth,
            $scaledHeight,
            $textWidth,
            $textHeight,
        );

        return $canvas;
    }
}
