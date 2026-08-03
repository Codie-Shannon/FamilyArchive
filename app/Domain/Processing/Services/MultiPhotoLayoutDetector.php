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
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('The source image could not be decoded for multi-photo analysis.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $sampleWidth = min(240, $width);
        $sampleHeight = max(1, (int) round($height * ($sampleWidth / max(1, $width))));
        if ($sampleHeight > 240) {
            $sampleHeight = 240;
            $sampleWidth = max(1, (int) round($width * ($sampleHeight / max(1, $height))));
        }

        $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
        if ($sample === false || ! imagecopyresampled($sample, $source, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height)) {
            throw new RuntimeException('The multi-photo analysis surface could not be initialized.');
        }

        $vertical = $this->bestSeam($sample, true);
        $horizontal = $this->bestSeam($sample, false);
        $threshold = 0.62;
        $hasVertical = $vertical['confidence'] >= $threshold;
        $hasHorizontal = $horizontal['confidence'] >= $threshold;
        $regions = [];

        if ($hasVertical && $hasHorizontal) {
            $x = (int) round($vertical['ratio'] * 10000);
            $y = (int) round($horizontal['ratio'] * 10000);
            $regions = [
                ['x' => 0, 'y' => 0, 'width' => $x, 'height' => $y, 'confidence' => min($vertical['confidence'], $horizontal['confidence'])],
                ['x' => $x, 'y' => 0, 'width' => 10000 - $x, 'height' => $y, 'confidence' => min($vertical['confidence'], $horizontal['confidence'])],
                ['x' => 0, 'y' => $y, 'width' => $x, 'height' => 10000 - $y, 'confidence' => min($vertical['confidence'], $horizontal['confidence'])],
                ['x' => $x, 'y' => $y, 'width' => 10000 - $x, 'height' => 10000 - $y, 'confidence' => min($vertical['confidence'], $horizontal['confidence'])],
            ];
        } elseif ($hasVertical) {
            $x = (int) round($vertical['ratio'] * 10000);
            $regions = [
                ['x' => 0, 'y' => 0, 'width' => $x, 'height' => 10000, 'confidence' => $vertical['confidence']],
                ['x' => $x, 'y' => 0, 'width' => 10000 - $x, 'height' => 10000, 'confidence' => $vertical['confidence']],
            ];
        } elseif ($hasHorizontal) {
            $y = (int) round($horizontal['ratio'] * 10000);
            $regions = [
                ['x' => 0, 'y' => 0, 'width' => 10000, 'height' => $y, 'confidence' => $horizontal['confidence']],
                ['x' => 0, 'y' => $y, 'width' => 10000, 'height' => 10000 - $y, 'confidence' => $horizontal['confidence']],
            ];
        }

        $confidence = $regions === [] ? max($vertical['confidence'], $horizontal['confidence']) : min(0.99, max($vertical['confidence'], $horizontal['confidence']));

        return [
            'detected' => count($regions) >= 2,
            'confidence' => round($confidence, 4),
            'method' => 'continuous_seam_and_edge_discontinuity_v1',
            'width' => $width,
            'height' => $height,
            'regions' => $regions,
            'signals' => ['vertical' => $vertical, 'horizontal' => $horizontal],
        ];
    }

    /** @return array{ratio:float,confidence:float,coverage:float,difference:float,gutter:float} */
    private function bestSeam(\GdImage $image, bool $vertical): array
    {
        $axis = $vertical ? imagesx($image) : imagesy($image);
        $cross = $vertical ? imagesy($image) : imagesx($image);
        $best = ['ratio' => 0.5, 'confidence' => 0.0, 'coverage' => 0.0, 'difference' => 0.0, 'gutter' => 0.0];

        for ($position = (int) floor($axis * 0.22); $position <= (int) ceil($axis * 0.78); $position += max(1, (int) floor($axis / 120))) {
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

            if ($confidence > $best['confidence']) {
                $best = [
                    'ratio' => round($position / max(1, $axis), 4),
                    'confidence' => round($confidence, 4),
                    'coverage' => round($coverage, 4),
                    'difference' => round($difference, 2),
                    'gutter' => round($gutter, 4),
                ];
            }
        }

        return $best;
    }

    /** @return array{int,int,int} */
    private function rgb(\GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }
}
