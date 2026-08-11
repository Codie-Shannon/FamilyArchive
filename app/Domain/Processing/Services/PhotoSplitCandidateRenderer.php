<?php

namespace App\Domain\Processing\Services;

use App\Domain\Processing\ValueObjects\RenderedSplitPhoto;
use GdImage;
use RuntimeException;
use Symfony\Component\Process\Process;

final class PhotoSplitCandidateRenderer
{
    /**
     * @param  list<array{x:int,y:int,width:int,height:int,rotation_degrees:float|int}>  $regions
     * @return list<RenderedSplitPhoto>
     */
    public function renderBatch(string $sourceBytes, array $regions): array
    {
        if ($regions === []) {
            return [];
        }
        $dimensions = @getimagesizefromstring($sourceBytes);
        if (! is_array($dimensions)) {
            throw new RuntimeException('The immutable source could not be decoded for split rendering.');
        }
        $sourceWidth = (int) $dimensions[0];
        $sourceHeight = (int) $dimensions[1];
        $sharp = $this->sharpExecutableFor($sourceWidth, $sourceHeight);
        if ($sharp !== null) {
            return $this->renderBatchWithSharp(
                $sharp,
                $sourceBytes,
                $sourceWidth,
                $sourceHeight,
                $regions,
            );
        }
        $imageMagick = $this->imageMagickExecutableFor($sourceWidth, $sourceHeight);
        $maximumSourcePixels = $imageMagick === null
            ? (int) config('archive.multi_photo.max_source_pixels', 250000000)
            : (int) config('archive.multi_photo.candidate_rendering.imagemagick_max_source_pixels', 250000000);
        if ($sourceWidth * $sourceHeight > $maximumSourcePixels) {
            throw new RuntimeException('The immutable source exceeds the split-rendering pixel limit.');
        }
        foreach ($regions as $region) {
            if ($region['width'] < 1 || $region['height'] < 1 || $region['x'] < 0 || $region['y'] < 0
                || $sourceWidth < $region['x'] + $region['width'] || $sourceHeight < $region['y'] + $region['height']) {
                throw new RuntimeException('A split region falls outside the immutable source.');
            }
        }
        if ($imageMagick === null) {
            return array_map(fn (array $region): RenderedSplitPhoto => $this->render(
                $sourceBytes,
                $region['x'],
                $region['y'],
                $region['width'],
                $region['height'],
                (float) $region['rotation_degrees'],
            ), $regions);
        }

        return $this->renderBatchWithImageMagick(
            $imageMagick,
            $sourceBytes,
            $sourceWidth,
            $sourceHeight,
            $regions,
        );
    }

    public function render(
        string $sourceBytes,
        int $x,
        int $y,
        int $width,
        int $height,
        float $manualRotationDegrees = 0.0,
    ): RenderedSplitPhoto {
        $dimensions = @getimagesizefromstring($sourceBytes);
        if (! is_array($dimensions)) {
            throw new RuntimeException('The immutable source could not be decoded for split rendering.');
        }
        $sourceWidth = (int) $dimensions[0];
        $sourceHeight = (int) $dimensions[1];
        $sharp = $this->sharpExecutableFor($sourceWidth, $sourceHeight);
        if ($sharp !== null) {
            return $this->renderBatchWithSharp(
                $sharp,
                $sourceBytes,
                $sourceWidth,
                $sourceHeight,
                [[
                    'x' => $x,
                    'y' => $y,
                    'width' => $width,
                    'height' => $height,
                    'rotation_degrees' => $manualRotationDegrees,
                ]],
            )[0];
        }
        $imageMagick = $this->imageMagickExecutableFor($sourceWidth, $sourceHeight);
        $maximumSourcePixels = $imageMagick === null
            ? (int) config('archive.multi_photo.max_source_pixels', 250000000)
            : (int) config('archive.multi_photo.candidate_rendering.imagemagick_max_source_pixels', 250000000);
        if ($sourceWidth * $sourceHeight > $maximumSourcePixels) {
            throw new RuntimeException('The immutable source exceeds the split-rendering pixel limit.');
        }
        if ($width < 1 || $height < 1 || $x < 0 || $y < 0 || $x + $width > $sourceWidth || $y + $height > $sourceHeight) {
            throw new RuntimeException('The split region falls outside the immutable source.');
        }

        if ($imageMagick !== null) {
            return $this->renderWithImageMagick(
                $imageMagick,
                $sourceBytes,
                $sourceWidth,
                $sourceHeight,
                $x,
                $y,
                $width,
                $height,
                $manualRotationDegrees,
            );
        }

        $previousLimit = ini_get('memory_limit');
        $configuredLimit = (string) config('archive.multi_photo.candidate_rendering.memory_limit', '512M');
        if ($configuredLimit !== '' && $this->mayRaiseMemoryLimit($previousLimit, $configuredLimit)) {
            ini_set('memory_limit', $configuredLimit);
        }

        $source = @imagecreatefromstring($sourceBytes);
        if (! $source instanceof GdImage) {
            $this->restoreMemoryLimit($previousLimit);
            throw new RuntimeException('The immutable source could not be decoded for split rendering.');
        }

        try {
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
            // The crop is self-contained after this copy. Releasing the full
            // decoded scan before rotation keeps large sources below the
            // production container's hard memory ceiling.
            imagedestroy($source);
            $source = null;

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
            if (abs($gdRotation) < 0.01) {
                $rotated = $working;
            } else {
                $rotated = $this->rotateExpanded($working, $gdRotation);
                imagedestroy($working);
            }

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
            $qualitySignals = $this->qualitySignals($final);
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
                    'quality_signals' => $qualitySignals,
                ],
            );
        } finally {
            if ($source instanceof GdImage) {
                imagedestroy($source);
            }
            $this->restoreMemoryLimit($previousLimit);
        }
    }

    private function renderWithImageMagick(
        string $executable,
        string $sourceBytes,
        int $sourceWidth,
        int $sourceHeight,
        int $x,
        int $y,
        int $width,
        int $height,
        float $manualRotationDegrees,
        ?string $preparedSourcePath = null,
    ): RenderedSplitPhoto {
        $ratio = max(0.0, (float) config('archive.multi_photo.candidate_rendering.padding_ratio', 0.08));
        $minimumPadding = max(0, (int) config('archive.multi_photo.candidate_rendering.minimum_padding_pixels', 8));
        $maximumPadding = max($minimumPadding, (int) config('archive.multi_photo.candidate_rendering.maximum_padding_pixels', 192));
        $paddingX = min($maximumPadding, max($minimumPadding, (int) ceil($width * $ratio)));
        $paddingY = min($maximumPadding, max($minimumPadding, (int) ceil($height * $ratio)));
        $workingWidth = $width + ($paddingX * 2);
        $workingHeight = $height + ($paddingY * 2);
        $requestedLeft = $x - $paddingX;
        $requestedTop = $y - $paddingY;
        $copyLeft = max(0, $requestedLeft);
        $copyTop = max(0, $requestedTop);
        $copyRight = min($sourceWidth, $x + $width + $paddingX);
        $copyBottom = min($sourceHeight, $y + $height + $paddingY);
        $copyWidth = max(1, $copyRight - $copyLeft);
        $copyHeight = max(1, $copyBottom - $copyTop);
        $destinationX = $copyLeft - $requestedLeft;
        $destinationY = $copyTop - $requestedTop;
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'familyarchive-split-'.bin2hex(random_bytes(8));
        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The disk-backed split renderer could not create its temporary workspace.');
        }

        $inputPath = $preparedSourcePath ?? $directory.DIRECTORY_SEPARATOR.'source.bin';
        $skewPath = $directory.DIRECTORY_SEPARATOR.'skew.png';
        $outputPath = $directory.DIRECTORY_SEPARATOR.'candidate.webp';
        $qualityPath = $directory.DIRECTORY_SEPARATOR.'quality.png';
        try {
            if ($preparedSourcePath === null && file_put_contents($inputPath, $sourceBytes, LOCK_EX) !== strlen($sourceBytes)) {
                throw new RuntimeException('The disk-backed split renderer could not stage the immutable source.');
            }

            $this->runImageMagick($executable, [
                $inputPath,
                '-crop', "{$width}x{$height}+{$x}+{$y}",
                '+repage',
                '-thumbnail', '1200x1200>',
                $skewPath,
            ]);
            $skewBytes = @file_get_contents($skewPath);
            $skewImage = is_string($skewBytes) ? @imagecreatefromstring($skewBytes) : false;
            if (! $skewImage instanceof GdImage) {
                throw new RuntimeException('The disk-backed split renderer could not inspect crop alignment.');
            }
            try {
                $skew = $this->detectSkew($skewImage, 0, 0, imagesx($skewImage), imagesy($skewImage));
            } finally {
                imagedestroy($skewImage);
            }
            $minimumConfidence = (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 0.55);
            $minimumDegrees = (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_degrees', 0.4);
            $maximumDegrees = (float) config('archive.multi_photo.candidate_rendering.maximum_deskew_degrees', 8.0);
            $deskewDegrees = $skew['confidence'] >= $minimumConfidence
                && abs($skew['degrees']) >= $minimumDegrees
                && abs($skew['degrees']) <= $maximumDegrees
                    ? -$skew['degrees']
                    : 0.0;
            $gdRotation = -$manualRotationDegrees + $deskewDegrees;
            // ImageMagick's positive rotation is clockwise; GD's is counter-clockwise.
            $clockwiseRotation = -$gdRotation;
            $radians = deg2rad(abs($gdRotation));
            $finalSafety = max(0, (int) config('archive.multi_photo.candidate_rendering.final_safety_pixels', 2));
            $targetWidth = (int) ceil(abs($width * cos($radians)) + abs($height * sin($radians))) + ($finalSafety * 2);
            $targetHeight = (int) ceil(abs($width * sin($radians)) + abs($height * cos($radians))) + ($finalSafety * 2);
            $rotatedWidth = (int) ceil(abs($workingWidth * cos($radians)) + abs($workingHeight * sin($radians)));
            $rotatedHeight = (int) ceil(abs($workingWidth * sin($radians)) + abs($workingHeight * cos($radians)));
            $targetWidth = min($rotatedWidth, max(1, $targetWidth));
            $targetHeight = min($rotatedHeight, max(1, $targetHeight));
            $finalX = max(0, (int) floor(($rotatedWidth - $targetWidth) / 2));
            $finalY = max(0, (int) floor(($rotatedHeight - $targetHeight) / 2));
            $arguments = [
                $inputPath,
                '-crop', "{$copyWidth}x{$copyHeight}+{$copyLeft}+{$copyTop}",
                '+repage',
                '-background', 'none',
            ];
            if ($destinationX > 0 || $destinationY > 0) {
                array_push($arguments, '-gravity', 'northwest', '-splice', "{$destinationX}x{$destinationY}+0+0");
            }
            array_push(
                $arguments,
                '-gravity', 'northwest',
                '-extent', "{$workingWidth}x{$workingHeight}",
            );
            if (abs($clockwiseRotation) >= 0.01) {
                array_push($arguments, '-gravity', 'center', '-rotate', (string) round($clockwiseRotation, 4));
            }
            array_push(
                $arguments,
                '-gravity', 'center',
                '-crop', "{$targetWidth}x{$targetHeight}+0+0",
                '+repage',
                '-quality', (string) ((int) config('archive.multi_photo.candidate_rendering.webp_quality', 90)),
                '-write', $outputPath,
                '-thumbnail', '640x640>',
                $qualityPath,
            );
            $this->runImageMagick($executable, $arguments);

            $output = @file_get_contents($outputPath);
            $finalDimensions = is_string($output) ? @getimagesizefromstring($output) : false;
            $qualityBytes = @file_get_contents($qualityPath);
            $qualityImage = is_string($qualityBytes) ? @imagecreatefromstring($qualityBytes) : false;
            if (! is_string($output) || $output === '' || ! is_array($finalDimensions) || ! $qualityImage instanceof GdImage) {
                throw new RuntimeException('The disk-backed split renderer produced an invalid candidate.');
            }
            $finalWidth = (int) $finalDimensions[0];
            $finalHeight = (int) $finalDimensions[1];
            try {
                $qualitySignals = $this->qualitySignals($qualityImage, $finalWidth, $finalHeight);
            } finally {
                imagedestroy($qualityImage);
            }

            return new RenderedSplitPhoto(
                bytes: $output,
                width: $finalWidth,
                height: $finalHeight,
                recipe: [
                    'pipeline_version' => $preparedSourcePath === null ? 3 : 4,
                    'rendering_backend' => $preparedSourcePath === null
                        ? 'imagemagick_disk_backed_v1'
                        : 'imagemagick_disk_cached_v2',
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
                    'quality_signals' => $qualitySignals,
                ],
            );
        } finally {
            $paths = [$skewPath, $outputPath, $qualityPath];
            if ($preparedSourcePath === null) {
                $paths[] = $inputPath;
            }
            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($directory);
        }
    }

    /**
     * @param  list<array{x:int,y:int,width:int,height:int,rotation_degrees:float|int}>  $regions
     * @return list<RenderedSplitPhoto>
     */
    private function renderBatchWithSharp(
        string $node,
        string $sourceBytes,
        int $sourceWidth,
        int $sourceHeight,
        array $regions,
    ): array {
        $maximumSourcePixels = (int) config('archive.multi_photo.candidate_rendering.sharp_max_source_pixels', 250000000);
        if ($sourceWidth * $sourceHeight > $maximumSourcePixels) {
            throw new RuntimeException('The immutable source exceeds the streaming split-rendering pixel limit.');
        }
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'familyarchive-split-sharp-'.bin2hex(random_bytes(8));
        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The streaming split renderer could not create its workspace.');
        }
        $inputPath = $directory.DIRECTORY_SEPARATOR.'source.bin';
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        try {
            if (file_put_contents($inputPath, $sourceBytes, LOCK_EX) !== strlen($sourceBytes)) {
                throw new RuntimeException('The streaming split renderer could not stage the immutable source.');
            }
            $manifestRegions = [];
            foreach ($regions as $index => $region) {
                $x = $region['x'];
                $y = $region['y'];
                $width = $region['width'];
                $height = $region['height'];
                if ($width < 1 || $height < 1 || $x < 0 || $y < 0
                    || $sourceWidth < $x + $width || $sourceHeight < $y + $height) {
                    throw new RuntimeException('A streaming split region falls outside the immutable source.');
                }
                $ratio = max(0.0, (float) config('archive.multi_photo.candidate_rendering.padding_ratio', 0.08));
                $minimumPadding = max(0, (int) config('archive.multi_photo.candidate_rendering.minimum_padding_pixels', 8));
                $maximumPadding = max($minimumPadding, (int) config('archive.multi_photo.candidate_rendering.maximum_padding_pixels', 192));
                $paddingX = min($maximumPadding, max($minimumPadding, (int) ceil($width * $ratio)));
                $paddingY = min($maximumPadding, max($minimumPadding, (int) ceil($height * $ratio)));
                $requestedLeft = $x - $paddingX;
                $requestedTop = $y - $paddingY;
                $copyLeft = max(0, $requestedLeft);
                $copyTop = max(0, $requestedTop);
                $copyRight = min($sourceWidth, $x + $width + $paddingX);
                $copyBottom = min($sourceHeight, $y + $height + $paddingY);
                $manifestRegions[] = [
                    'index' => $index,
                    'x' => $x,
                    'y' => $y,
                    'width' => $width,
                    'height' => $height,
                    'padding_x' => $paddingX,
                    'padding_y' => $paddingY,
                    'working_width' => $width + ($paddingX * 2),
                    'working_height' => $height + ($paddingY * 2),
                    'copy_left' => $copyLeft,
                    'copy_top' => $copyTop,
                    'copy_width' => max(1, $copyRight - $copyLeft),
                    'copy_height' => max(1, $copyBottom - $copyTop),
                    'destination_x' => $copyLeft - $requestedLeft,
                    'destination_y' => $copyTop - $requestedTop,
                    'manual_rotation_degrees' => (float) $region['rotation_degrees'],
                    'output_path' => $directory.DIRECTORY_SEPARATOR."candidate-{$index}.webp",
                    'quality_path' => $directory.DIRECTORY_SEPARATOR."quality-{$index}.png",
                ];
            }
            $manifest = [
                'input_path' => $inputPath,
                'maximum_source_pixels' => $maximumSourcePixels,
                'minimum_deskew_confidence' => (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 0.55),
                'minimum_deskew_degrees' => (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_degrees', 0.4),
                'maximum_deskew_degrees' => (float) config('archive.multi_photo.candidate_rendering.maximum_deskew_degrees', 8.0),
                'final_safety_pixels' => max(0, (int) config('archive.multi_photo.candidate_rendering.final_safety_pixels', 2)),
                'webp_quality' => (int) config('archive.multi_photo.candidate_rendering.webp_quality', 90),
                'regions' => $manifestRegions,
            ];
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($manifestPath, $manifestJson, LOCK_EX) !== strlen($manifestJson)) {
                throw new RuntimeException('The streaming split renderer could not stage its manifest.');
            }
            $script = base_path('tools/family_photo_sharp_render.mjs');
            $process = new Process([$node, $script, $manifestPath]);
            $process->setTimeout(max(30, (int) config('archive.multi_photo.candidate_rendering.sharp_timeout_seconds', 900)));
            $process->run();
            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput().' '.$process->getOutput());
                throw new RuntimeException('The streaming split renderer failed: '.mb_substr($error, 0, 500));
            }
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $resultByIndex = [];
            foreach ($result['results'] ?? [] as $row) {
                $resultByIndex[(int) $row['index']] = $row;
            }
            $rendered = [];
            foreach ($manifestRegions as $region) {
                $index = $region['index'];
                $row = $resultByIndex[$index] ?? null;
                $output = @file_get_contents($region['output_path']);
                $finalDimensions = is_string($output) ? @getimagesizefromstring($output) : false;
                $qualityBytes = @file_get_contents($region['quality_path']);
                $qualityImage = is_string($qualityBytes) ? @imagecreatefromstring($qualityBytes) : false;
                if (! is_array($row) || ! is_string($output) || $output === '' || ! is_array($finalDimensions) || ! $qualityImage instanceof GdImage) {
                    throw new RuntimeException('The streaming split renderer produced an invalid candidate.');
                }
                $finalWidth = (int) $finalDimensions[0];
                $finalHeight = (int) $finalDimensions[1];
                try {
                    $qualitySignals = $this->qualitySignals($qualityImage, $finalWidth, $finalHeight);
                } finally {
                    imagedestroy($qualityImage);
                }
                $rendered[] = new RenderedSplitPhoto(
                    bytes: $output,
                    width: $finalWidth,
                    height: $finalHeight,
                    recipe: [
                        'pipeline_version' => 6,
                        'rendering_backend' => 'sharp_libvips_streaming_v1',
                        'operation_order' => ['padded_extract', 'independent_rotate', 'final_edge_crop'],
                        'source_dimensions' => ['width' => $sourceWidth, 'height' => $sourceHeight],
                        'requested_bounds_pixels' => [
                            'x' => $region['x'],
                            'y' => $region['y'],
                            'width' => $region['width'],
                            'height' => $region['height'],
                        ],
                        'padding_pixels' => ['x' => $region['padding_x'], 'y' => $region['padding_y']],
                        'manual_rotation_degrees_clockwise' => round($region['manual_rotation_degrees'], 2),
                        'deskew' => [
                            'detected_degrees' => (float) $row['skew']['degrees'],
                            'confidence' => (float) $row['skew']['confidence'],
                            'applied_degrees' => round((float) $row['deskew_degrees'], 2),
                        ],
                        'render_rotation_degrees' => round((float) $row['gd_rotation'], 2),
                        'final_crop' => [
                            'x' => (int) $row['final_x'],
                            'y' => (int) $row['final_y'],
                            'width' => $finalWidth,
                            'height' => $finalHeight,
                            'safety_pixels' => $manifest['final_safety_pixels'],
                        ],
                        'clipping_guard' => 'rotate_before_final_crop',
                        'quality_signals' => $qualitySignals,
                    ],
                );
            }

            return $rendered;
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    /**
     * @param  list<array{x:int,y:int,width:int,height:int,rotation_degrees:float|int}>  $regions
     * @return list<RenderedSplitPhoto>
     */
    private function renderBatchWithImageMagick(
        string $executable,
        string $sourceBytes,
        int $sourceWidth,
        int $sourceHeight,
        array $regions,
    ): array {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'familyarchive-split-batch-'.bin2hex(random_bytes(8));
        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The disk-backed split renderer could not create its batch workspace.');
        }
        $inputPath = $directory.DIRECTORY_SEPARATOR.'source.bin';
        try {
            if (file_put_contents($inputPath, $sourceBytes, LOCK_EX) !== strlen($sourceBytes)) {
                throw new RuntimeException('The disk-backed split renderer could not stage the immutable batch source.');
            }
            $geometry = [];
            $previewArguments = [$inputPath];
            foreach ($regions as $index => $region) {
                $x = $region['x'];
                $y = $region['y'];
                $width = $region['width'];
                $height = $region['height'];
                $ratio = max(0.0, (float) config('archive.multi_photo.candidate_rendering.padding_ratio', 0.08));
                $minimumPadding = max(0, (int) config('archive.multi_photo.candidate_rendering.minimum_padding_pixels', 8));
                $maximumPadding = max($minimumPadding, (int) config('archive.multi_photo.candidate_rendering.maximum_padding_pixels', 192));
                $paddingX = min($maximumPadding, max($minimumPadding, (int) ceil($width * $ratio)));
                $paddingY = min($maximumPadding, max($minimumPadding, (int) ceil($height * $ratio)));
                $requestedLeft = $x - $paddingX;
                $requestedTop = $y - $paddingY;
                $copyLeft = max(0, $requestedLeft);
                $copyTop = max(0, $requestedTop);
                $copyRight = min($sourceWidth, $x + $width + $paddingX);
                $copyBottom = min($sourceHeight, $y + $height + $paddingY);
                $geometry[$index] = [
                    'x' => $x,
                    'y' => $y,
                    'width' => $width,
                    'height' => $height,
                    'padding_x' => $paddingX,
                    'padding_y' => $paddingY,
                    'working_width' => $width + ($paddingX * 2),
                    'working_height' => $height + ($paddingY * 2),
                    'copy_left' => $copyLeft,
                    'copy_top' => $copyTop,
                    'copy_width' => max(1, $copyRight - $copyLeft),
                    'copy_height' => max(1, $copyBottom - $copyTop),
                    'destination_x' => $copyLeft - $requestedLeft,
                    'destination_y' => $copyTop - $requestedTop,
                    'manual_rotation' => (float) $region['rotation_degrees'],
                    'skew_path' => $directory.DIRECTORY_SEPARATOR."skew-{$index}.png",
                    'output_path' => $directory.DIRECTORY_SEPARATOR."candidate-{$index}.webp",
                    'quality_path' => $directory.DIRECTORY_SEPARATOR."quality-{$index}.png",
                ];
                array_push(
                    $previewArguments,
                    '(', '+clone',
                    '-crop', "{$width}x{$height}+{$x}+{$y}",
                    '+repage',
                    '-thumbnail', '1200x1200>',
                    '-write', $geometry[$index]['skew_path'],
                    '+delete', ')',
                );
            }
            $previewArguments[] = 'null:';
            $this->runImageMagick($executable, $previewArguments);

            $renderArguments = [$inputPath];
            foreach ($geometry as $index => &$item) {
                $skewBytes = @file_get_contents($item['skew_path']);
                $skewImage = is_string($skewBytes) ? @imagecreatefromstring($skewBytes) : false;
                if (! $skewImage instanceof GdImage) {
                    throw new RuntimeException('The disk-backed split renderer could not inspect batch crop alignment.');
                }
                try {
                    $skew = $this->detectSkew($skewImage, 0, 0, imagesx($skewImage), imagesy($skewImage));
                } finally {
                    imagedestroy($skewImage);
                }
                $minimumConfidence = (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 0.55);
                $minimumDegrees = (float) config('archive.multi_photo.candidate_rendering.minimum_deskew_degrees', 0.4);
                $maximumDegrees = (float) config('archive.multi_photo.candidate_rendering.maximum_deskew_degrees', 8.0);
                $deskewDegrees = $skew['confidence'] >= $minimumConfidence
                    && abs($skew['degrees']) >= $minimumDegrees
                    && abs($skew['degrees']) <= $maximumDegrees
                        ? -$skew['degrees']
                        : 0.0;
                $gdRotation = -$item['manual_rotation'] + $deskewDegrees;
                $clockwiseRotation = -$gdRotation;
                $radians = deg2rad(abs($gdRotation));
                $finalSafety = max(0, (int) config('archive.multi_photo.candidate_rendering.final_safety_pixels', 2));
                $targetWidth = (int) ceil(abs($item['width'] * cos($radians)) + abs($item['height'] * sin($radians))) + ($finalSafety * 2);
                $targetHeight = (int) ceil(abs($item['width'] * sin($radians)) + abs($item['height'] * cos($radians))) + ($finalSafety * 2);
                $rotatedWidth = (int) ceil(abs($item['working_width'] * cos($radians)) + abs($item['working_height'] * sin($radians)));
                $rotatedHeight = (int) ceil(abs($item['working_width'] * sin($radians)) + abs($item['working_height'] * cos($radians)));
                $targetWidth = min($rotatedWidth, max(1, $targetWidth));
                $targetHeight = min($rotatedHeight, max(1, $targetHeight));
                $item['skew'] = $skew;
                $item['deskew_degrees'] = $deskewDegrees;
                $item['gd_rotation'] = $gdRotation;
                $item['final_safety'] = $finalSafety;
                $item['target_width'] = $targetWidth;
                $item['target_height'] = $targetHeight;
                $item['final_x'] = max(0, (int) floor(($rotatedWidth - $targetWidth) / 2));
                $item['final_y'] = max(0, (int) floor(($rotatedHeight - $targetHeight) / 2));
                array_push(
                    $renderArguments,
                    '(', '+clone',
                    '-crop', "{$item['copy_width']}x{$item['copy_height']}+{$item['copy_left']}+{$item['copy_top']}",
                    '+repage',
                    '-background', 'none',
                );
                if ($item['destination_x'] > 0 || $item['destination_y'] > 0) {
                    array_push($renderArguments, '-gravity', 'northwest', '-splice', "{$item['destination_x']}x{$item['destination_y']}+0+0");
                }
                array_push(
                    $renderArguments,
                    '-gravity', 'northwest',
                    '-extent', "{$item['working_width']}x{$item['working_height']}",
                );
                if (abs($clockwiseRotation) >= 0.01) {
                    array_push($renderArguments, '-gravity', 'center', '-rotate', (string) round($clockwiseRotation, 4));
                }
                array_push(
                    $renderArguments,
                    '-gravity', 'center',
                    '-crop', "{$targetWidth}x{$targetHeight}+0+0",
                    '+repage',
                    '-quality', (string) ((int) config('archive.multi_photo.candidate_rendering.webp_quality', 90)),
                    '-write', $item['output_path'],
                    '-thumbnail', '640x640>',
                    '-write', $item['quality_path'],
                    '+delete', ')',
                );
            }
            unset($item);
            $renderArguments[] = 'null:';
            $this->runImageMagick($executable, $renderArguments);

            $rendered = [];
            foreach ($geometry as $item) {
                $output = @file_get_contents($item['output_path']);
                $finalDimensions = is_string($output) ? @getimagesizefromstring($output) : false;
                $qualityBytes = @file_get_contents($item['quality_path']);
                $qualityImage = is_string($qualityBytes) ? @imagecreatefromstring($qualityBytes) : false;
                if (! is_string($output) || $output === '' || ! is_array($finalDimensions) || ! $qualityImage instanceof GdImage) {
                    throw new RuntimeException('The disk-backed split renderer produced an invalid batch candidate.');
                }
                $finalWidth = (int) $finalDimensions[0];
                $finalHeight = (int) $finalDimensions[1];
                try {
                    $qualitySignals = $this->qualitySignals($qualityImage, $finalWidth, $finalHeight);
                } finally {
                    imagedestroy($qualityImage);
                }
                $rendered[] = new RenderedSplitPhoto(
                    bytes: $output,
                    width: $finalWidth,
                    height: $finalHeight,
                    recipe: [
                        'pipeline_version' => 5,
                        'rendering_backend' => 'imagemagick_single_decode_batch_v3',
                        'operation_order' => ['padded_extract', 'independent_rotate', 'final_edge_crop'],
                        'source_dimensions' => ['width' => $sourceWidth, 'height' => $sourceHeight],
                        'requested_bounds_pixels' => [
                            'x' => $item['x'],
                            'y' => $item['y'],
                            'width' => $item['width'],
                            'height' => $item['height'],
                        ],
                        'padding_pixels' => ['x' => $item['padding_x'], 'y' => $item['padding_y']],
                        'manual_rotation_degrees_clockwise' => round($item['manual_rotation'], 2),
                        'deskew' => [
                            'detected_degrees' => $item['skew']['degrees'],
                            'confidence' => $item['skew']['confidence'],
                            'applied_degrees' => round($item['deskew_degrees'], 2),
                        ],
                        'render_rotation_degrees' => round($item['gd_rotation'], 2),
                        'final_crop' => [
                            'x' => $item['final_x'],
                            'y' => $item['final_y'],
                            'width' => $finalWidth,
                            'height' => $finalHeight,
                            'safety_pixels' => $item['final_safety'],
                        ],
                        'clipping_guard' => 'rotate_before_final_crop',
                        'quality_signals' => $qualitySignals,
                    ],
                );
            }

            return $rendered;
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    /** @return array{status:string,minimum_dimensions_pass:bool,transparent_edge_ratio:float,detail_score:float,checks:list<string>} */
    private function qualitySignals(GdImage $image, ?int $actualWidth = null, ?int $actualHeight = null): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minimumCheckWidth = $actualWidth ?? $width;
        $minimumCheckHeight = $actualHeight ?? $height;
        $minimumDimension = max(1, (int) config('archive.multi_photo.candidate_rendering.quality_minimum_dimension_pixels', 160));
        $maximumTransparentEdgeRatio = max(0.0, min(1.0, (float) config('archive.multi_photo.candidate_rendering.quality_maximum_transparent_edge_ratio', 0.20)));
        $minimumDetailScore = max(0.0, (float) config('archive.multi_photo.candidate_rendering.quality_minimum_detail_score', 2.0));
        $stepX = max(1, (int) floor($width / 160));
        $stepY = max(1, (int) floor($height / 160));
        $edgeSamples = 0;
        $transparentEdges = 0;
        $detailTotal = 0.0;
        $detailSamples = 0;

        for ($x = 0; $x < $width; $x += $stepX) {
            foreach ([0, $height - 1] as $y) {
                $edgeSamples++;
                if ($this->alpha($this->colorAt($image, $x, $y)) >= 120) {
                    $transparentEdges++;
                }
            }
        }
        for ($y = 0; $y < $height; $y += $stepY) {
            foreach ([0, $width - 1] as $x) {
                $edgeSamples++;
                if ($this->alpha($this->colorAt($image, $x, $y)) >= 120) {
                    $transparentEdges++;
                }
            }
        }
        for ($y = 0; $y + $stepY < $height; $y += $stepY) {
            for ($x = 0; $x + $stepX < $width; $x += $stepX) {
                $current = $this->luma($this->colorAt($image, $x, $y));
                $detailTotal += abs($current - $this->luma($this->colorAt($image, $x + $stepX, $y)));
                $detailTotal += abs($current - $this->luma($this->colorAt($image, $x, $y + $stepY)));
                $detailSamples += 2;
            }
        }

        $minimumDimensionsPass = min($minimumCheckWidth, $minimumCheckHeight) >= $minimumDimension;
        $transparentEdgeRatio = $transparentEdges / $edgeSamples;
        $detailScore = $detailTotal / $detailSamples;
        $attention = ! $minimumDimensionsPass || $transparentEdgeRatio > $maximumTransparentEdgeRatio || $detailScore < $minimumDetailScore;

        return [
            'status' => $attention ? 'attention' : 'automatic_checks_passed_visual_review_required',
            'minimum_dimensions_pass' => $minimumDimensionsPass,
            'transparent_edge_ratio' => round($transparentEdgeRatio, 4),
            'detail_score' => round($detailScore, 2),
            'checks' => ['source_bounds', 'minimum_region_size', 'transparent_or_blank_edge', 'detail_or_blur_proxy', 'rotation_clipping_guard'],
        ];
    }

    private function alpha(int $color): int
    {
        return ($color >> 24) & 0x7F;
    }

    private function colorAt(GdImage $image, int $x, int $y): int
    {
        $color = imagecolorat($image, $x, $y);
        if ($color === false) {
            throw new RuntimeException('The rendered split photo could not be sampled for quality checks.');
        }

        return $color;
    }

    private function luma(int $color): float
    {
        return (0.2126 * (($color >> 16) & 0xFF))
            + (0.7152 * (($color >> 8) & 0xFF))
            + (0.0722 * ($color & 0xFF));
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

    private function imageMagickExecutable(): ?string
    {
        $configured = trim((string) config('archive.multi_photo.candidate_rendering.imagemagick_path', ''));

        return $configured !== '' && is_file($configured) && is_executable($configured)
            ? $configured
            : null;
    }

    private function imageMagickExecutableFor(int $sourceWidth, int $sourceHeight): ?string
    {
        $minimumPixels = max(1, (int) config('archive.multi_photo.candidate_rendering.imagemagick_minimum_source_pixels', 45000001));

        return $sourceWidth * $sourceHeight >= $minimumPixels
            ? $this->imageMagickExecutable()
            : null;
    }

    private function sharpExecutableFor(int $sourceWidth, int $sourceHeight): ?string
    {
        $minimumPixels = max(1, (int) config('archive.multi_photo.candidate_rendering.sharp_minimum_source_pixels', 45000001));
        if ($sourceWidth * $sourceHeight < $minimumPixels) {
            return null;
        }
        $node = trim((string) config('archive.multi_photo.candidate_rendering.sharp_node_path', ''));
        $script = base_path('tools/family_photo_sharp_render.mjs');
        $package = is_file(base_path('node_modules/sharp/package.json'))
            ? base_path('node_modules/sharp/package.json')
            : base_path('tools/node-runtime/node_modules/sharp/package.json');

        return $node !== '' && is_file($node) && is_executable($node) && is_file($script) && is_file($package)
            ? $node
            : null;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }

    /** @param list<string> $arguments */
    private function runImageMagick(string $executable, array $arguments): void
    {
        $process = new Process([
            $executable,
            '-limit', 'thread', '1',
            '-limit', 'memory', (string) config('archive.multi_photo.candidate_rendering.imagemagick_memory_limit', '64MiB'),
            '-limit', 'map', (string) config('archive.multi_photo.candidate_rendering.imagemagick_map_limit', '128MiB'),
            '-limit', 'disk', (string) config('archive.multi_photo.candidate_rendering.imagemagick_disk_limit', '8GiB'),
            ...$arguments,
        ]);
        $process->setTimeout(max(30, (int) config('archive.multi_photo.candidate_rendering.imagemagick_timeout_seconds', 900)));
        $process->run();
        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput().' '.$process->getOutput());
            throw new RuntimeException('The disk-backed split renderer failed: '.mb_substr($error, 0, 400));
        }
    }

    private function mayRaiseMemoryLimit(string|false $current, string $configured): bool
    {
        $currentBytes = $this->memoryBytes($current);
        $configuredBytes = $this->memoryBytes($configured);

        return $configuredBytes === -1 || ($currentBytes !== -1 && $configuredBytes > $currentBytes);
    }

    private function memoryBytes(string|false $value): int
    {
        if (! is_string($value) || trim($value) === '' || trim($value) === '-1') {
            return -1;
        }
        $normalized = strtoupper(trim($value));
        $number = (float) $normalized;
        $multiplier = str_ends_with($normalized, 'G') ? 1024 ** 3
            : (str_ends_with($normalized, 'M') ? 1024 ** 2
                : (str_ends_with($normalized, 'K') ? 1024 : 1));

        return (int) floor($number * $multiplier);
    }

    private function restoreMemoryLimit(string|false $previousLimit): void
    {
        if (is_string($previousLimit) && $previousLimit !== '') {
            ini_set('memory_limit', $previousLimit);
        }
    }
}
