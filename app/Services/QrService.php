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

    public function pngBinary(string $payload, int $size = 480): string
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

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        return $png;
    }

    public function pngDataUri(string $payload, int $size = 480): string
    {
        return 'data:image/png;base64,'.base64_encode($this->pngBinary($payload, $size));
    }
}
