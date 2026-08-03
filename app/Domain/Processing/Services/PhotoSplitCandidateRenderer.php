<?php

namespace App\Domain\Processing\Services;

use App\Domain\Processing\ValueObjects\RenderedSplitPhoto;
use GdImage;
use RuntimeException;

final class PhotoSplitCandidateRenderer
{
    public function render(
        string $sourceBytes,
        int $x,
        int $y,
        int $width,
        int $height,
        float $manualRotationDegrees = 0.0,
    ): RenderedSplitPhoto {
        $previousLimit = ini_get('memory_limit');
        $configuredLimit = (string) config('archive.multi_photo.candidate_rendering.memory_limit', '512M');
        if ($configuredLimit !== '') {
            ini_set('memory_limit', $configuredLimit);
        }

        $source = @imagecreatefromstring($sourceBytes);
        if (! $source instanceof GdImage) {
            $this->restoreMemoryLimit($previousLimit);
            throw new RuntimeException('The immutable source could not be decoded for split rendering.');
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth * $sourceHeight > (int) config('archive.multi_photo.max_source_pixels', 45000000)) {
                throw new RuntimeException('The immutable source exceeds the split-rendering pixel limit.');
            }
            if ($width < 1 || $height < 1 || $x < 0 || $y < 0 || $x + $width > $sourceWidth || $y + $height > $sourceHeight) {
                throw new RuntimeException('The split region falls outside the immutable source.');
            }

            $ratio = max(0.0, (float) config('archive.multi_photo.candidate_rendering.padding_ratio', 0.08));
            $minimumPadding = max(0, (int) config('archive.multi_photo.candidate_rendering.minimum_padding_pixels', 8));
            $maximumPadding = max($minimumPadding, (int) config('archive.multi_photo.candidate_rendering.maximum_padding_pixels', 192));
            $paddingX = min($maximumPadding, max($minimumPadding, (int) ceil($width * $ratio)));
            $paddingY = min($maximumPadding, max($minimumPadding, (int) ceil($height * $ratio)));

            $working = $this->transparentCanvas($width + ($paddingX * 2), $height + ($paddingY * 2));
            $requestedLeft = $x - $paddingX;
            $requestedTop = $y - $paddingY;
            $copyLeft = max(0, $requestedLeft);
            $copyTop = max(0, $requestedTop);
            $copyRight = min($sourceWidth, $x + $width + $paddingX);
            $copyBottom = min($sourceHeight, $y + $height + $paddingY);
            $destinationX = $copyLeft - $requestedLeft;
            $destinationY = $copyTop - $requestedTop;
            imagecopy(
                $working,
                $source,
                $destinationX,
                $destinationY,
                $copyLeft,
                $copyTop,
                max(1, $copyRight - $copyLeft),
                max(1, $copyBottom - $copyTop),
            );

            $skew = $this->detectSkew($working, $paddingX, $paddingY, $width, $height);
            $minimumConfidence = (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 0.55);
            $minimumDegrees = (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_degrees', 0.4);
            $maximumDegrees = (float) config('archive.multi_photo.candidate_rendering.maximum_deskew_degrees', 8.0);
            $deskewDegrees = $skew['confidence'] >= $minimumConfidence
                && abs($skew['degrees']) >= $minimumDegrees
                && abs($skew['degrees']) <= $maximumDegrees
                    ? -$skew['degrees']
                    : 0.0;

            // Positive manual values mean clockwise to the reviewer. GD uses
            // positive values for counter-clockwise rotation.
            $gdRotation = -$manualRotationDegrees + $deskewDegrees;
            $rotated = $this->rotateExpanded($working, $gdRotation);
            imagedestroy($working);

            $radians = deg2rad(abs($gdRotation));
            $finalSafety = max(0, (int) config('archive.multi_photo.candidate_rendering.final_safety_pixels', 2));
            $targetWidth = (int) ceil(abs($width * cos($radians)) + abs($height * sin($radians))) + ($finalSafety * 2);
            $targetHeight = (int) ceil(abs($width * sin($radians)) + abs($height * cos($radians))) + ($finalSafety * 2);
            $targetWidth = min(imagesx($rotated), max(1, $targetWidth));
            $targetHeight = min(imagesy($rotated), max(1, $targetHeight));
            $finalX = max(0, (int) floor((imagesx($rotated) - $targetWidth) / 2));
            $finalY = max(0, (int) floor((imagesy($rotated) - $targetHeight) / 2));
            $final = imagecrop($rotated, [
                'x' => $finalX,
                'y' => $finalY,
                'width' => $targetWidth,
                'height' => $targetHeight,
            ]);
            imagedestroy($rotated);
            if (! $final instanceof GdImage) {
                throw new RuntimeException('The rotated split region could not be cropped safely.');
            }

            ob_start();
            $encoded = imagewebp($final, null, (int) config('archive.multi_photo.candidate_rendering.webp_quality', 90));
            $output = ob_get_clean();
            $finalWidth = imagesx($final);
            $finalHeight = imagesy($final);
            imagedestroy($final);
            if (! $encoded || ! is_string($output) || $output === '') {
                throw new RuntimeException('The split region could not be encoded.');
            }

            return new RenderedSplitPhoto(
                bytes: $output,
                width: $finalWidth,
                height: $finalHeight,
                recipe: [
                    'pipeline_version' => 2,
                    'operation_order' => ['padded_extract', 'independent_rotate', 'final_edge_crop'],
                    'source_dimensions' => ['width' => $sourceWidth, 'height' => $sourceHeight],
                    'requested_bounds_pixels' => compact('x', 'y', 'width', 'height'),
                    'padding_pixels' => ['x' => $paddingX, 'y' => $paddingY],
                    'manual_rotation_degrees_clockwise' => round($manualRotationDegrees, 2),
                    'deskew' => [
                        'detected_degrees' => $skew['degrees'],
                        'confidence' => $skew['confidence'],
                        'applied_degrees' => round($deskewDegrees, 2),
                    ],
                    'render_rotation_degrees' => round($gdRotation, 2),
                    'final_crop' => [
                        'x' => $finalX,
                        'y' => $finalY,
                        'width' => $finalWidth,
                        'height' => $finalHeight,
                        'safety_pixels' => $finalSafety,
                    ],
                    'clipping_guard' => 'rotate_before_final_crop',
                ],
            );
        } finally {
            imagedestroy($source);
            $this->restoreMemoryLimit($previousLimit);
        }
    }

    private function transparentCanvas(int $width, int $height): GdImage
    {
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('The split-photo render canvas dimensions must be positive.');
        }

        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('The padded split rendering surface could not be initialized.');
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent === false ? 0 : $transparent);
        imagealphablending($canvas, true);

        return $canvas;
    }

    private function rotateExpanded(GdImage $image, float $degrees): GdImage
    {
        if (abs($degrees) < 0.01) {
            $copy = $this->transparentCanvas(imagesx($image), imagesy($image));
            imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

            return $copy;
        }
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, $degrees, $transparent === false ? 0 : $transparent);
        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('The split photo could not be rotated.');
        }
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /** @return array{degrees:float,confidence:float} */
    private function detectSkew(GdImage $image, int $offsetX, int $offsetY, int $width, int $height): array
    {
        $step = max(2, (int) ceil($height / 120));
        $points = [];
        $corner = imagecolorat($image, $offsetX + min(2, $width - 1), $offsetY + min(2, $height - 1));
        $background = [($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF];

        for ($localY = 0; $localY < $height; $localY += $step) {
            for ($localX = 0; $localX < (int) ($width * 0.45); $localX += $step) {
                $color = imagecolorat($image, $offsetX + $localX, $offsetY + $localY);
                $distance = abs((($color >> 16) & 0xFF) - $background[0])
                    + abs((($color >> 8) & 0xFF) - $background[1])
                    + abs(($color & 0xFF) - $background[2]);
                if ($distance >= 100) {
                    $points[] = [$localY, $localX];
                    break;
                }
            }
        }
        if (count($points) < 12) {
            return ['degrees' => 0.0, 'confidence' => 0.0];
        }
        $meanY = array_sum(array_column($points, 0)) / count($points);
        $meanX = array_sum(array_column($points, 1)) / count($points);
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($points as [$pointY, $pointX]) {
            $numerator += ($pointY - $meanY) * ($pointX - $meanX);
            $denominator += ($pointY - $meanY) ** 2;
        }
        $degrees = rad2deg(atan($denominator > 0 ? $numerator / $denominator : 0.0));

        return [
            'degrees' => round($degrees, 2),
            'confidence' => round(min(0.9, count($points) / 100), 2),
        ];
    }

    private function restoreMemoryLimit(string|false $previousLimit): void
    {
        if (is_string($previousLimit) && $previousLimit !== '') {
            ini_set('memory_limit', $previousLimit);
        }
    }
}
