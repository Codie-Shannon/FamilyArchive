<?php

namespace App\Domain\Processing\Services;

use RuntimeException;

final class MultiPhotoLayoutDetector
{
    /**
     * @return array{detected:bool,confidence:float,method:string,width:int,height:int,regions:list<array{x:int,y:int,width:int,height:int,confidence:float}>,signals:array<string,mixed>}
     */
    public function analyze(string $bytes): array
    {
        $size = @getimagesizefromstring($bytes);
        if (! is_array($size)) {
            throw new RuntimeException('The source image could not be inspected for multi-photo analysis.');
        }
        $width = (int) $size[0];
        $height = (int) $size[1];
        $maximumPixels = (int) $this->setting('archive.multi_photo.max_source_pixels', 45000000);
        if ($width < 1 || $height < 1 || $width > intdiv(max(1, $maximumPixels), $height)) {
            throw new RuntimeException('The source image exceeds the safe multi-photo analysis boundary.');
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('The source image could not be decoded for multi-photo analysis.');
        }
        try {
            $sampleWidth = min(240, $width);
            $sampleHeight = max(1, (int) round($height * ($sampleWidth / max(1, $width))));
            if ($sampleHeight > 240) {
                $sampleHeight = 240;
                $sampleWidth = max(1, (int) round($width * ($sampleHeight / max(1, $height))));
            }

            $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
            if ($sample === false) {
                throw new RuntimeException('The multi-photo analysis surface could not be initialized.');
            }
            try {
                if (! imagecopyresampled($sample, $source, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height)) {
                    throw new RuntimeException('The multi-photo analysis surface could not be initialized.');
                }
                $verticalCandidates = $this->seamCandidates($sample, true);
                $horizontalCandidates = $this->seamCandidates($sample, false);
                $adaptiveLayout = $this->adaptiveRegions($sample);
            } finally {
                unset($sample);
            }
        } finally {
            unset($source);
        }

        $vertical = $verticalCandidates[0] ?? $this->emptySignal();
        $horizontal = $horizontalCandidates[0] ?? $this->emptySignal();
        $verticalSeams = array_values(array_filter(
            $verticalCandidates,
            fn (array $signal): bool => $this->axisHighConfidence($signal, 'grid'),
        ));
        $horizontalSeams = array_values(array_filter(
            $horizontalCandidates,
            fn (array $signal): bool => $this->axisHighConfidence($signal, 'grid'),
        ));

        if ($verticalSeams === [] || $horizontalSeams === []) {
            $verticalSeams = array_values(array_filter(
                $verticalSeams,
                fn (array $signal): bool => $this->axisHighConfidence($signal, 'single'),
            ));
            $horizontalSeams = array_values(array_filter(
                $horizontalSeams,
                fn (array $signal): bool => $this->axisHighConfidence($signal, 'single'),
            ));
        }

        $gridRegions = $this->regions($verticalSeams, $horizontalSeams);
        $adaptiveSelected = count($adaptiveLayout['regions']) > count($gridRegions);
        $regions = $adaptiveSelected ? $adaptiveLayout['regions'] : $gridRegions;
        $selectedSignals = [...$verticalSeams, ...$horizontalSeams];
        $confidence = $adaptiveSelected
            ? $adaptiveLayout['confidence']
            : ($selectedSignals === []
                ? max($vertical['confidence'], $horizontal['confidence'])
                : min(array_column($selectedSignals, 'confidence')));

        return [
            'detected' => count($regions) >= 2,
            'confidence' => round($confidence, 4),
            'method' => 'adaptive_partition_seam_graph_v4',
            'width' => $width,
            'height' => $height,
            'regions' => $regions,
            'signals' => [
                'vertical' => $vertical,
                'horizontal' => $horizontal,
                'vertical_candidates' => $verticalCandidates,
                'horizontal_candidates' => $horizontalCandidates,
                'selected_vertical' => $verticalSeams,
                'selected_horizontal' => $horizontalSeams,
                'adaptive_splits' => $adaptiveLayout['splits'],
                'adaptive_layout_selected' => $adaptiveSelected,
                'layout_validated' => count($regions) >= 2,
            ],
        ];
    }

    /** @param array<string, mixed> $analysis */
    public function isHighConfidenceAnalysis(array $analysis): bool
    {
        $signals = $analysis['signals'] ?? null;
        if (! is_array($signals)) {
            return false;
        }
        if (($signals['layout_validated'] ?? false) === true) {
            return ($analysis['detected'] ?? false) === true
                && count(is_array($analysis['regions'] ?? null) ? $analysis['regions'] : []) >= 2;
        }

        $vertical = is_array($signals['vertical'] ?? null) ? $signals['vertical'] : [];
        $horizontal = is_array($signals['horizontal'] ?? null) ? $signals['horizontal'] : [];
        $verticalGrid = $this->axisHighConfidence($vertical, 'grid');
        $horizontalGrid = $this->axisHighConfidence($horizontal, 'grid');

        return ($verticalGrid && $horizontalGrid)
            || ($verticalGrid && $this->axisHighConfidence($vertical, 'single'))
            || ($horizontalGrid && $this->axisHighConfidence($horizontal, 'single'));
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function axisHighConfidence(array $signal, string $profile): bool
    {
        $thresholds = (array) $this->setting("archive.multi_photo.{$profile}", match ($profile) {
            'grid' => [
                'minimum_confidence' => 0.77,
                'minimum_coverage' => 0.78,
                'minimum_difference' => 40,
                'minimum_gutter' => 0.78,
            ],
            default => [
                'minimum_confidence' => 0.88,
                'minimum_coverage' => 0.92,
                'minimum_difference' => 62,
                'minimum_gutter' => 0.90,
            ],
        });
        $confidence = (float) ($signal['confidence'] ?? 0.0);
        $coverage = (float) ($signal['coverage'] ?? 0.0);
        $difference = (float) ($signal['difference'] ?? 0.0);
        $gutter = (float) ($signal['gutter'] ?? 0.0);

        return $confidence >= (float) ($thresholds['minimum_confidence'] ?? 1.0)
            && (
                $gutter >= (float) ($thresholds['minimum_gutter'] ?? 1.0)
                || (
                    $coverage >= (float) ($thresholds['minimum_coverage'] ?? 1.0)
                    && $difference >= (float) ($thresholds['minimum_difference'] ?? PHP_FLOAT_MAX)
                )
            );
    }

    /**
     * @param  list<array{ratio:float,confidence:float,coverage:float,difference:float,gutter:float}>  $vertical
     * @param  list<array{ratio:float,confidence:float,coverage:float,difference:float,gutter:float}>  $horizontal
     * @return list<array{x:int,y:int,width:int,height:int,confidence:float}>
     */
    private function regions(array $vertical, array $horizontal): array
    {
        if ($vertical === [] && $horizontal === []) {
            return [];
        }

        usort($vertical, static fn (array $a, array $b): int => $a['ratio'] <=> $b['ratio']);
        usort($horizontal, static fn (array $a, array $b): int => $a['ratio'] <=> $b['ratio']);
        $xCuts = [0, ...array_map(static fn (array $signal): int => (int) round($signal['ratio'] * 10000), $vertical), 10000];
        $yCuts = [0, ...array_map(static fn (array $signal): int => (int) round($signal['ratio'] * 10000), $horizontal), 10000];
        $selectedSignals = [...$vertical, ...$horizontal];
        if ($selectedSignals === []) {
            return [];
        }
        $confidence = min(array_column($selectedSignals, 'confidence'));
        $regions = [];
        for ($row = 0; $row < count($yCuts) - 1; $row++) {
            for ($column = 0; $column < count($xCuts) - 1; $column++) {
                $regions[] = [
                    'x' => $xCuts[$column],
                    'y' => $yCuts[$row],
                    'width' => $xCuts[$column + 1] - $xCuts[$column],
                    'height' => $yCuts[$row + 1] - $yCuts[$row],
                    'confidence' => $confidence,
                ];
            }
        }

        return $regions;
    }

    /**
     * Recursively partitions scanner sheets so different rows or columns may use
     * different photo counts. This complements the whole-sheet grid detector.
     *
     * @return array{
     *     regions:list<array{x:int,y:int,width:int,height:int,confidence:float}>,
     *     splits:list<array<string,mixed>>,
     *     confidence:float
     * }
     */
    private function adaptiveRegions(\GdImage $image): array
    {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $maximumRegions = max(2, (int) $this->setting('archive.multi_photo.maximum_regions', 32));
        $minimumWidth = max(18, (int) floor($imageWidth * 0.09));
        $minimumHeight = max(18, (int) floor($imageHeight * 0.09));
        $leaves = [[
            'x' => 0,
            'y' => 0,
            'width' => $imageWidth,
            'height' => $imageHeight,
            'confidence' => 1.0,
        ]];
        $splits = [];

        while (count($leaves) < $maximumRegions) {
            $best = null;
            foreach ($leaves as $leafIndex => $leaf) {
                foreach ([true, false] as $vertical) {
                    if (($vertical && $leaf['width'] < $minimumWidth * 2)
                        || (! $vertical && $leaf['height'] < $minimumHeight * 2)) {
                        continue;
                    }

                    $candidates = $this->boundedSeamCandidates(
                        $image,
                        $leaf['x'],
                        $leaf['y'],
                        $leaf['width'],
                        $leaf['height'],
                        $vertical,
                    );
                    foreach ($candidates as $candidate) {
                        if (! $this->axisHighConfidence($candidate, 'grid')) {
                            continue;
                        }

                        $axisLength = $vertical ? $leaf['width'] : $leaf['height'];
                        $cutOffset = (int) round($candidate['ratio'] * $axisLength);
                        $before = $cutOffset;
                        $after = $axisLength - $cutOffset;
                        $minimum = $vertical ? $minimumWidth : $minimumHeight;
                        if ($before < $minimum || $after < $minimum) {
                            continue;
                        }

                        $score = $candidate['confidence'] + (0.04 * $candidate['gutter']);
                        if ($best === null || $score > $best['score']) {
                            $best = [
                                'leaf_index' => $leafIndex,
                                'vertical' => $vertical,
                                'candidate' => $candidate,
                                'cut_offset' => $cutOffset,
                                'score' => $score,
                            ];
                        }
                    }
                }
            }

            if ($best === null) {
                break;
            }

            $leaf = $leaves[$best['leaf_index']];
            $candidate = $best['candidate'];
            $cutOffset = $best['cut_offset'];
            $confidence = min($leaf['confidence'], $candidate['confidence']);
            if ($best['vertical']) {
                $children = [
                    [...$leaf, 'width' => $cutOffset, 'confidence' => $confidence],
                    [
                        ...$leaf,
                        'x' => $leaf['x'] + $cutOffset,
                        'width' => $leaf['width'] - $cutOffset,
                        'confidence' => $confidence,
                    ],
                ];
            } else {
                $children = [
                    [...$leaf, 'height' => $cutOffset, 'confidence' => $confidence],
                    [
                        ...$leaf,
                        'y' => $leaf['y'] + $cutOffset,
                        'height' => $leaf['height'] - $cutOffset,
                        'confidence' => $confidence,
                    ],
                ];
            }

            array_splice($leaves, $best['leaf_index'], 1, $children);
            $splits[] = [
                'orientation' => $best['vertical'] ? 'vertical' : 'horizontal',
                'scope' => [
                    'x' => round($leaf['x'] / max(1, $imageWidth), 4),
                    'y' => round($leaf['y'] / max(1, $imageHeight), 4),
                    'width' => round($leaf['width'] / max(1, $imageWidth), 4),
                    'height' => round($leaf['height'] / max(1, $imageHeight), 4),
                ],
                'ratio' => $candidate['ratio'],
                'confidence' => $candidate['confidence'],
                'coverage' => $candidate['coverage'],
                'difference' => $candidate['difference'],
                'gutter' => $candidate['gutter'],
            ];
        }

        if ($splits === []) {
            return ['regions' => [], 'splits' => [], 'confidence' => 0.0];
        }

        usort($leaves, static fn (array $a, array $b): int => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);
        $regions = array_map(
            static fn (array $leaf): array => [
                'x' => (int) round(($leaf['x'] / max(1, $imageWidth)) * 10000),
                'y' => (int) round(($leaf['y'] / max(1, $imageHeight)) * 10000),
                'width' => (int) round(($leaf['width'] / max(1, $imageWidth)) * 10000),
                'height' => (int) round(($leaf['height'] / max(1, $imageHeight)) * 10000),
                'confidence' => round($leaf['confidence'], 4),
            ],
            $leaves,
        );

        return [
            'regions' => $regions,
            'splits' => $splits,
            'confidence' => round(min(array_column($splits, 'confidence')), 4),
        ];
    }

    /**
     * @return list<array{ratio:float,confidence:float,coverage:float,difference:float,gutter:float}>
     */
    private function boundedSeamCandidates(
        \GdImage $image,
        int $x,
        int $y,
        int $width,
        int $height,
        bool $vertical,
    ): array {
        $axis = $vertical ? $width : $height;
        $cross = $vertical ? $height : $width;
        if ($axis < 8 || $cross < 8) {
            return [];
        }

        $measured = [];
        $step = max(1, (int) floor($axis / 90));
        for ($position = (int) floor($axis * 0.08); $position <= (int) ceil($axis * 0.92); $position += $step) {
            $differences = [];
            $lineLuma = [];
            for ($offset = 0; $offset < $cross; $offset++) {
                $axisBefore = max(0, $position - 2);
                $axisAfter = min($axis - 1, $position + 2);
                if ($vertical) {
                    $a = $this->rgb($image, $x + $axisBefore, $y + $offset);
                    $b = $this->rgb($image, $x + $axisAfter, $y + $offset);
                    $middle = $this->rgb($image, $x + $position, $y + $offset);
                } else {
                    $a = $this->rgb($image, $x + $offset, $y + $axisBefore);
                    $b = $this->rgb($image, $x + $offset, $y + $axisAfter);
                    $middle = $this->rgb($image, $x + $offset, $y + $position);
                }
                $differences[] = (abs($a[0] - $b[0]) + abs($a[1] - $b[1]) + abs($a[2] - $b[2])) / 3;
                $lineLuma[] = ($middle[0] + $middle[1] + $middle[2]) / 3;
            }

            $difference = array_sum($differences) / max(1, count($differences));
            $coverage = count(array_filter($differences, static fn (float $value): bool => $value >= 28)) / max(1, count($differences));
            $mean = array_sum($lineLuma) / max(1, count($lineLuma));
            $variance = array_sum(array_map(static fn (float $value): float => ($value - $mean) ** 2, $lineLuma)) / max(1, count($lineLuma));
            $uniformity = max(0.0, 1.0 - (sqrt($variance) / 90));
            $brightOrDark = max($mean / 255, 1 - ($mean / 255));
            $gutter = $uniformity * $brightOrDark;
            $confidence = min(1.0, (0.50 * $coverage) + (0.35 * min(1.0, $difference / 72)) + (0.15 * $gutter));
            $measured[] = [
                'ratio' => round($position / max(1, $axis), 4),
                'confidence' => round($confidence, 4),
                'coverage' => round($coverage, 4),
                'difference' => round($difference, 2),
                'gutter' => round($gutter, 4),
            ];
        }

        usort($measured, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
        $minimumSpacing = (float) $this->setting('archive.multi_photo.minimum_seam_spacing', 0.08);
        $selected = [];
        foreach ($measured as $candidate) {
            if (! array_any(
                $selected,
                static fn (array $existing): bool => abs($existing['ratio'] - $candidate['ratio']) < $minimumSpacing,
            )) {
                $selected[] = $candidate;
            }
            if (count($selected) >= 3) {
                break;
            }
        }

        return $selected;
    }

    /** @return list<array{ratio:float,confidence:float,coverage:float,difference:float,gutter:float}> */
    private function seamCandidates(\GdImage $image, bool $vertical): array
    {
        $axis = $vertical ? imagesx($image) : imagesy($image);
        $cross = $vertical ? imagesy($image) : imagesx($image);
        $measured = [];
        $step = max(1, (int) floor($axis / 120));

        for ($position = (int) floor($axis * 0.08); $position <= (int) ceil($axis * 0.92); $position += $step) {
            $differences = [];
            $lineLuma = [];
            for ($offset = 0; $offset < $cross; $offset++) {
                $a = $vertical ? $this->rgb($image, max(0, $position - 2), $offset) : $this->rgb($image, $offset, max(0, $position - 2));
                $b = $vertical ? $this->rgb($image, min($axis - 1, $position + 2), $offset) : $this->rgb($image, $offset, min($axis - 1, $position + 2));
                $middle = $vertical ? $this->rgb($image, $position, $offset) : $this->rgb($image, $offset, $position);
                $differences[] = (abs($a[0] - $b[0]) + abs($a[1] - $b[1]) + abs($a[2] - $b[2])) / 3;
                $lineLuma[] = ($middle[0] + $middle[1] + $middle[2]) / 3;
            }

            $difference = array_sum($differences) / max(1, count($differences));
            $coverage = count(array_filter($differences, static fn (float $value): bool => $value >= 28)) / max(1, count($differences));
            $mean = array_sum($lineLuma) / max(1, count($lineLuma));
            $variance = array_sum(array_map(static fn (float $value): float => ($value - $mean) ** 2, $lineLuma)) / max(1, count($lineLuma));
            $uniformity = max(0.0, 1.0 - (sqrt($variance) / 90));
            $brightOrDark = max($mean / 255, 1 - ($mean / 255));
            $gutter = $uniformity * $brightOrDark;
            $confidence = min(1.0, (0.50 * $coverage) + (0.35 * min(1.0, $difference / 72)) + (0.15 * $gutter));

            $measured[] = [
                'ratio' => round($position / max(1, $axis), 4),
                'confidence' => round($confidence, 4),
                'coverage' => round($coverage, 4),
                'difference' => round($difference, 2),
                'gutter' => round($gutter, 4),
            ];
        }

        usort($measured, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
        $minimumSpacing = (float) $this->setting('archive.multi_photo.minimum_seam_spacing', 0.08);
        $maximumSeams = (int) $this->setting('archive.multi_photo.maximum_axis_seams', 7);
        $selected = [];
        foreach ($measured as $candidate) {
            $tooClose = array_any(
                $selected,
                static fn (array $existing): bool => abs($existing['ratio'] - $candidate['ratio']) < $minimumSpacing,
            );
            if (! $tooClose) {
                $selected[] = $candidate;
            }
            if (count($selected) >= $maximumSeams) {
                break;
            }
        }

        return $selected;
    }

    /** @return array{ratio:float,confidence:float,coverage:float,difference:float,gutter:float} */
    private function emptySignal(): array
    {
        return ['ratio' => 0.5, 'confidence' => 0.0, 'coverage' => 0.0, 'difference' => 0.0, 'gutter' => 0.0];
    }

    /** @return array{int,int,int} */
    private function rgb(\GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    private function setting(string $key, mixed $default): mixed
    {
        return app()->bound('config') ? config($key, $default) : $default;
    }
}
