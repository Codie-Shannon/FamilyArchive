<?php

namespace App\Domain\Derivatives\Services;

use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\ValueObjects\EncodedDerivative;
use GdImage;
use Symfony\Component\Process\Process;

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
        $originalLimit = ini_get('memory_limit');
        $processingLimit = (string) config('archive.photo_derivatives.memory_limit', '512M');

        if ($processingLimit !== '' && preg_match('/^(?:-1|\d+[KMG]?)$/i', $processingLimit)) {
            ini_set('memory_limit', $processingLimit);
        }

        try {
            return $this->encodeWithProcessingMemory($sourceBytes, $sourceMime, $maxLongSide, $quality);
        } finally {
            if ($originalLimit !== '') {
                ini_set('memory_limit', $originalLimit);
            }
        }
    }

    private function encodeWithProcessingMemory(string $sourceBytes, string $sourceMime, int $maxLongSide, int $quality): EncodedDerivative
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

        $imageMagick = trim((string) config('archive.photo_derivatives.imagemagick_path', ''));
        $imageMagickThreshold = max(1, (int) config('archive.photo_derivatives.imagemagick_minimum_source_pixels', 30000000));
        if ($pixelCount >= $imageMagickThreshold && $imageMagick !== '' && is_file($imageMagick) && is_executable($imageMagick)) {
            return $this->encodeWithImageMagick($imageMagick, $sourceBytes, $sourceMime, $maxLongSide, $quality);
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
                if ($transparent === false) {
                    throw new DerivativeGenerationException('The derivative render surface could not be initialized.');
                }
                imagefill($canvas, 0, 0, $transparent);

                imagecopyresampled(
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
                );

                ob_start();
                $encoded = imagewebp($canvas, null, $quality);
                $bytes = ob_get_clean();

                if (! $encoded || $bytes === '') {
                    throw new DerivativeGenerationException('The WebP derivative encoder failed.');
                }
            } finally {
                unset($canvas);
            }
        } finally {
            unset($image);
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

    private function encodeWithImageMagick(
        string $executable,
        string $sourceBytes,
        string $sourceMime,
        int $maxLongSide,
        int $quality,
    ): EncodedDerivative {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'familyarchive-derivative-'.bin2hex(random_bytes(8));
        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new DerivativeGenerationException('The disk-backed derivative encoder could not create its workspace.');
        }
        $sourcePath = $directory.DIRECTORY_SEPARATOR.'source.bin';
        $outputPath = $directory.DIRECTORY_SEPARATOR.'output.webp';
        try {
            if (file_put_contents($sourcePath, $sourceBytes, LOCK_EX) !== strlen($sourceBytes)) {
                throw new DerivativeGenerationException('The disk-backed derivative encoder could not stage its source.');
            }
            $orientation = $this->readOrientation($sourceBytes, $sourceMime);
            $arguments = [
                $executable,
                '-limit', 'thread', '1',
                '-limit', 'memory', (string) config('archive.photo_derivatives.imagemagick_memory_limit', '64MiB'),
                '-limit', 'map', (string) config('archive.photo_derivatives.imagemagick_map_limit', '128MiB'),
                '-limit', 'disk', (string) config('archive.photo_derivatives.imagemagick_disk_limit', '8GiB'),
            ];
            if (strtolower($sourceMime) === 'image/jpeg') {
                $decodeSide = max($maxLongSide, (int) ceil($maxLongSide * 1.25));
                array_push($arguments, '-define', "jpeg:size={$decodeSide}x{$decodeSide}");
            }
            array_push(
                $arguments,
                $sourcePath,
                '-auto-orient',
                '-resize', "{$maxLongSide}x{$maxLongSide}>",
                '-strip',
                '-quality', (string) $quality,
                $outputPath,
            );
            $process = new Process($arguments);
            $process->setTimeout(max(30, (int) config('archive.photo_derivatives.imagemagick_timeout_seconds', 900)));
            $process->run();
            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput().' '.$process->getOutput());
                throw new DerivativeGenerationException('The disk-backed derivative encoder failed: '.mb_substr($error, 0, 400));
            }
            $bytes = @file_get_contents($outputPath);
            $outputFacts = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
            if (! is_string($bytes) || $bytes === '' || ! is_array($outputFacts)
                || $outputFacts['mime'] !== 'image/webp'
                || max((int) $outputFacts[0], (int) $outputFacts[1]) > $maxLongSide) {
                throw new DerivativeGenerationException('The disk-backed WebP derivative failed integrity verification.');
            }

            return new EncodedDerivative(
                $bytes,
                (int) $outputFacts[0],
                (int) $outputFacts[1],
                $quality,
                $maxLongSide,
                $orientation,
                $orientation !== 1,
                'imagemagick/disk-backed-v1',
            );
        } finally {
            foreach ([$sourcePath, $outputPath] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($directory);
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

        unset($image);

        return $rotated;
    }
}
