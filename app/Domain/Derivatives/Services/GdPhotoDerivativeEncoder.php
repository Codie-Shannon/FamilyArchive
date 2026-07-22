<?php

namespace App\Domain\Derivatives\Services;

use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\ValueObjects\EncodedDerivative;
use GdImage;

final class GdPhotoDerivativeEncoder
{
    public function assertSupported(): void
    {
        if (
            ! extension_loaded('gd')
            || ! function_exists('imagecreatefromstring')
            || ! function_exists('imagewebp')
            || ! function_exists('imagecreatetruecolor')
        ) {
            throw new DerivativeGenerationException('Group 09 requires PHP GD with WebP encoding support.');
        }
    }

    public function encoderName(): string
    {
        $version = function_exists('gd_info') ? gd_info()['GD Version'] ?? 'unknown' : 'unknown';

        return 'php-gd/'.preg_replace('/[^A-Za-z0-9._ -]/', '', (string) $version);
    }

    public function encode(string $sourceBytes, string $sourceMime, int $maxLongSide, int $quality): EncodedDerivative
    {
        $this->assertSupported();

        $sourceSize = strlen($sourceBytes);
        $maximumBytes = (int) config('archive.photo_derivatives.max_source_bytes', 104857600);
        if ($sourceSize < 1 || $sourceSize > $maximumBytes) {
            throw new DerivativeGenerationException('The original exceeds the configured derivative source byte limit.');
        }

        $facts = @getimagesizefromstring($sourceBytes);
        if (! is_array($facts) || $facts[0] === 0 || $facts[1] === 0) {
            throw new DerivativeGenerationException('The approved original could not be decoded as an image.');
        }

        $pixelCount = $facts[0] * $facts[1];
        $maximumPixels = (int) config('archive.photo_derivatives.max_source_pixels', 80000000);
        if ($pixelCount > $maximumPixels) {
            throw new DerivativeGenerationException('The original exceeds the configured derivative pixel limit.');
        }

        $image = @imagecreatefromstring($sourceBytes);
        if (! $image instanceof GdImage) {
            throw new DerivativeGenerationException('The approved original image decoder failed closed.');
        }

        $orientation = $this->readOrientation($sourceBytes, $sourceMime);
        $orientationApplied = $orientation !== 1;

        try {
            $image = $this->applyOrientation($image, $orientation);
            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);
            $longest = max($sourceWidth, $sourceHeight);
            $scale = min(1.0, $maxLongSide / $longest);
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if (! $canvas instanceof GdImage) {
                throw new DerivativeGenerationException('The derivative render surface could not be created.');
            }

            try {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                if ($transparent === false || ! imagefill($canvas, 0, 0, $transparent)) {
                    throw new DerivativeGenerationException('The derivative render surface could not be initialized.');
                }

                if (! imagecopyresampled(
                    $canvas,
                    $image,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $sourceWidth,
                    $sourceHeight,
                )) {
                    throw new DerivativeGenerationException('The derivative resize operation failed.');
                }

                ob_start();
                $encoded = imagewebp($canvas, null, $quality);
                $bytes = ob_get_clean();

                if (! $encoded || $bytes === '') {
                    throw new DerivativeGenerationException('The WebP derivative encoder failed.');
                }
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($image);
        }

        $outputFacts = @getimagesizefromstring($bytes);
        if (
            ! is_array($outputFacts)
            || $outputFacts['mime'] !== 'image/webp'
            || (int) $outputFacts[0] !== $targetWidth
            || (int) $outputFacts[1] !== $targetHeight
        ) {
            throw new DerivativeGenerationException('The encoded WebP failed integrity verification.');
        }

        return new EncodedDerivative(
            $bytes,
            $targetWidth,
            $targetHeight,
            $quality,
            $maxLongSide,
            $orientation,
            $orientationApplied,
            $this->encoderName(),
        );
    }

    private function readOrientation(string $bytes, string $mime): int
    {
        if (! in_array(strtolower($mime), ['image/jpeg', 'image/tiff'], true) || ! function_exists('exif_read_data')) {
            return 1;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'fa-g09-exif-');
        if ($temporary === false) {
            return 1;
        }

        try {
            if (file_put_contents($temporary, $bytes) === false) {
                return 1;
            }

            $exif = @exif_read_data($temporary, 'IFD0', true, false);
            if (! is_array($exif)) {
                return 1;
            }

            $orientation = $exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1;

            return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
        } finally {
            @unlink($temporary);
        }
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 3) {
            $image = $this->rotate($image, 180);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        } elseif ($orientation === 5) {
            $image = $this->rotate($image, -90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 6) {
            $image = $this->rotate($image, -90);
        } elseif ($orientation === 7) {
            $image = $this->rotate($image, 90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 8) {
            $image = $this->rotate($image, 90);
        }

        return $image;
    }

    private function rotate(GdImage $image, int $degrees): GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);
        if (! $rotated instanceof GdImage) {
            throw new DerivativeGenerationException('The source orientation could not be applied.');
        }

        imagedestroy($image);

        return $rotated;
    }
}
