<?php

use App\Domain\Processing\Services\MultiPhotoLayoutDetector;

function splitFixtureColor(GdImage $image, int $red, int $green, int $blue): int
{
    $color = imagecolorallocate(
        $image,
        max(0, min(255, $red)),
        max(0, min(255, $green)),
        max(0, min(255, $blue)),
    );
    if ($color === false) {
        throw new RuntimeException('Unable to allocate a split fixture colour.');
    }

    return $color;
}

function splitFixture(bool $withGutters): string
{
    $image = imagecreatetruecolor(800, 600);
    $colors = [
        splitFixtureColor($image, 170, 45, 45),
        splitFixtureColor($image, 35, 120, 180),
        splitFixtureColor($image, 55, 145, 70),
        splitFixtureColor($image, 180, 120, 25),
    ];
    imagefilledrectangle($image, 0, 0, 399, 299, $colors[0]);
    imagefilledrectangle($image, 400, 0, 799, 299, $colors[1]);
    imagefilledrectangle($image, 0, 300, 399, 599, $colors[2]);
    imagefilledrectangle($image, 400, 300, 799, 599, $colors[3]);
    if ($withGutters) {
        $white = splitFixtureColor($image, 250, 250, 250);
        imagefilledrectangle($image, 394, 0, 406, 599, $white);
        imagefilledrectangle($image, 0, 294, 799, 306, $white);
    }
    ob_start();
    imagejpeg($image, null, 94);
    $bytes = ob_get_clean();

    return $bytes;
}

function twoPhotoFixture(): string
{
    $image = imagecreatetruecolor(800, 600);
    $left = splitFixtureColor($image, 155, 60, 45);
    $right = splitFixtureColor($image, 40, 115, 175);
    $white = splitFixtureColor($image, 250, 250, 250);
    imagefilledrectangle($image, 0, 0, 392, 599, $left);
    imagefilledrectangle($image, 393, 0, 407, 599, $white);
    imagefilledrectangle($image, 408, 0, 799, 599, $right);
    ob_start();
    imagejpeg($image, null, 94);
    $bytes = ob_get_clean();

    return $bytes;
}

function ordinarySceneFixture(): string
{
    $image = imagecreatetruecolor(800, 600);
    $background = splitFixtureColor($image, 125, 112, 96);
    $wall = splitFixtureColor($image, 155, 141, 122);
    $floor = splitFixtureColor($image, 104, 82, 64);
    $person = splitFixtureColor($image, 62, 79, 112);
    $window = splitFixtureColor($image, 176, 194, 201);
    imagefilledrectangle($image, 0, 0, 799, 599, $background);
    imagefilledrectangle($image, 0, 0, 799, 379, $wall);
    imagefilledrectangle($image, 0, 380, 799, 599, $floor);
    imagefilledrectangle($image, 80, 65, 265, 270, $window);
    imagefilledellipse($image, 505, 245, 90, 110, $person);
    imagefilledrectangle($image, 455, 300, 565, 535, $person);
    imageline($image, 0, 380, 799, 398, splitFixtureColor($image, 116, 93, 73));
    ob_start();
    imagejpeg($image, null, 94);
    $bytes = ob_get_clean();

    return $bytes;
}

/**
 * @param  non-empty-list<positive-int>  $columnWidths
 * @param  non-empty-list<positive-int>  $rowHeights
 */
function variableGridFixture(array $columnWidths, array $rowHeights): string
{
    $width = array_sum($columnWidths);
    $height = array_sum($rowHeights);
    $image = imagecreatetruecolor($width, $height);
    $palette = [
        [175, 45, 45], [35, 120, 180], [55, 150, 70], [185, 120, 25],
        [115, 65, 160], [30, 160, 155], [185, 65, 125], [80, 95, 175],
        [155, 90, 40], [40, 145, 110], [150, 55, 80], [70, 130, 185],
    ];
    $index = 0;
    $y = 0;
    foreach ($rowHeights as $rowHeight) {
        $x = 0;
        foreach ($columnWidths as $columnWidth) {
            [$red, $green, $blue] = $palette[$index % count($palette)];
            imagefilledrectangle(
                $image,
                $x,
                $y,
                $x + $columnWidth - 1,
                $y + $rowHeight - 1,
                splitFixtureColor($image, $red, $green, $blue),
            );
            $x += $columnWidth;
            $index++;
        }
        $y += $rowHeight;
    }
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    return $bytes;
}

it('detects a four-photo layout separated by borders', function (): void {
    $analysis = app(MultiPhotoLayoutDetector::class)->analyze(splitFixture(true));

    expect($analysis['detected'])->toBeTrue()
        ->and($analysis['regions'])->toHaveCount(4)
        ->and($analysis['confidence'])->toBeGreaterThanOrEqual(0.62);
});

it('detects a borderless four-photo layout from continuous edge discontinuities', function (): void {
    $analysis = app(MultiPhotoLayoutDetector::class)->analyze(splitFixture(false));

    expect($analysis['detected'])->toBeTrue()
        ->and($analysis['regions'])->toHaveCount(4)
        ->and($analysis['method'])->toBe('adaptive_partition_seam_graph_v4')
        ->and(app(MultiPhotoLayoutDetector::class)->isHighConfidenceAnalysis($analysis))->toBeTrue();
});

it('detects an uneven six-photo grid without assuming equal cells', function (): void {
    $analysis = app(MultiPhotoLayoutDetector::class)->analyze(variableGridFixture([240, 330, 230], [260, 340]));
    expect($analysis['detected'])->toBeTrue()
        ->and($analysis['regions'])->toHaveCount(6)
        ->and($analysis['signals']['selected_vertical'])->toHaveCount(2)
        ->and($analysis['signals']['selected_horizontal'])->toHaveCount(1);
});

it('detects an eight-photo scanner grid with several row seams', function (): void {
    $analysis = app(MultiPhotoLayoutDetector::class)->analyze(variableGridFixture([370, 430], [135, 165, 145, 155]));
    expect($analysis['detected'])->toBeTrue()
        ->and($analysis['regions'])->toHaveCount(8)
        ->and($analysis['signals']['selected_vertical'])->toHaveCount(1)
        ->and($analysis['signals']['selected_horizontal'])->toHaveCount(3);
});

it('detects a scanner mosaic whose rows have different column layouts', function (): void {
    $image = imagecreatetruecolor(900, 600);
    $palette = [
        [175, 45, 45], [35, 120, 180],
        [55, 150, 70], [185, 120, 25], [115, 65, 160],
    ];
    $white = splitFixtureColor($image, 250, 250, 250);
    imagefilledrectangle($image, 0, 0, 899, 599, $white);
    $topWidths = [355, 545];
    $bottomWidths = [240, 310, 350];
    $index = 0;
    $x = 0;
    foreach ($topWidths as $cellWidth) {
        [$red, $green, $blue] = $palette[$index++];
        imagefilledrectangle($image, $x, 0, $x + $cellWidth - 1, 293, splitFixtureColor($image, $red, $green, $blue));
        $x += $cellWidth;
    }
    $x = 0;
    foreach ($bottomWidths as $cellWidth) {
        [$red, $green, $blue] = $palette[$index++];
        imagefilledrectangle($image, $x, 307, $x + $cellWidth - 1, 599, splitFixtureColor($image, $red, $green, $blue));
        $x += $cellWidth;
    }
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    $analysis = app(MultiPhotoLayoutDetector::class)->analyze($bytes);

    expect($analysis['detected'])->toBeTrue()
        ->and($analysis['regions'])->toHaveCount(5)
        ->and($analysis['signals']['adaptive_layout_selected'])->toBeTrue()
        ->and($analysis['signals']['adaptive_splits'])->toHaveCount(4);
});

it('detects a strongly separated two-photo layout', function (): void {
    $analysis = app(MultiPhotoLayoutDetector::class)->analyze(twoPhotoFixture());

    expect($analysis['detected'])->toBeTrue()
        ->and($analysis['regions'])->toHaveCount(2);
});

it('does not classify a normal family scene as a multi-photo source', function (): void {
    $analysis = app(MultiPhotoLayoutDetector::class)->analyze(ordinarySceneFixture());

    expect($analysis['detected'])->toBeFalse()
        ->and($analysis['regions'])->toBe([])
        ->and(app(MultiPhotoLayoutDetector::class)->isHighConfidenceAnalysis($analysis))->toBeFalse();
});

it('rejects legacy weak split signals during reclassification', function (): void {
    $analysis = [
        'signals' => [
            'vertical' => ['confidence' => 0.71, 'coverage' => 0.52, 'difference' => 43, 'gutter' => 0.35],
            'horizontal' => ['confidence' => 0.68, 'coverage' => 0.48, 'difference' => 39, 'gutter' => 0.31],
        ],
    ];

    expect(app(MultiPhotoLayoutDetector::class)->isHighConfidenceAnalysis($analysis))->toBeFalse();
});

it('keeps narrow single-axis layouts out of automatic review', function (): void {
    $analysis = [
        'detected' => true,
        'regions' => [
            ['x' => 0, 'y' => 0, 'width' => 7680, 'height' => 10000],
            ['x' => 7680, 'y' => 0, 'width' => 939, 'height' => 10000],
            ['x' => 8619, 'y' => 0, 'width' => 1381, 'height' => 10000],
        ],
        'signals' => [
            'layout_validated' => true,
            'adaptive_layout_selected' => false,
            'selected_vertical' => [['ratio' => 0.768], ['ratio' => 0.8619]],
            'selected_horizontal' => [],
        ],
    ];

    expect(app(MultiPhotoLayoutDetector::class)->isHighConfidenceAnalysis($analysis))->toBeFalse();
});

it('accepts balanced two-photo geometry for automatic review', function (): void {
    $analysis = [
        'detected' => true,
        'regions' => [
            ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000],
            ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000],
        ],
        'signals' => [
            'layout_validated' => true,
            'adaptive_layout_selected' => false,
            'selected_vertical' => [['ratio' => 0.5]],
            'selected_horizontal' => [],
        ],
    ];

    expect(app(MultiPhotoLayoutDetector::class)->isHighConfidenceAnalysis($analysis))->toBeTrue();
});

it('rejects imbalanced two-photo geometry from automatic review', function (): void {
    $analysis = [
        'detected' => true,
        'regions' => [
            ['x' => 0, 'y' => 0, 'width' => 1708, 'height' => 10000],
            ['x' => 1708, 'y' => 0, 'width' => 8292, 'height' => 10000],
        ],
        'signals' => [
            'layout_validated' => true,
            'adaptive_layout_selected' => true,
            'selected_vertical' => [],
            'selected_horizontal' => [],
        ],
    ];

    expect(app(MultiPhotoLayoutDetector::class)->isHighConfidenceAnalysis($analysis))->toBeFalse();
});

it('rejects over-segmented and tiny-region layouts from automatic review', function (): void {
    $detector = app(MultiPhotoLayoutDetector::class);
    $overSegmented = [
        'detected' => true,
        'regions' => array_fill(0, 11, ['x' => 0, 'y' => 0, 'width' => 2000, 'height' => 2000]),
        'signals' => ['layout_validated' => true, 'adaptive_layout_selected' => true],
    ];
    $tinyRegion = [
        'detected' => true,
        'regions' => [
            ['x' => 0, 'y' => 0, 'width' => 8000, 'height' => 10000],
            ['x' => 8000, 'y' => 0, 'width' => 1160, 'height' => 1208],
            ['x' => 9160, 'y' => 0, 'width' => 840, 'height' => 10000],
        ],
        'signals' => ['layout_validated' => true, 'adaptive_layout_selected' => true],
    ];

    expect($detector->isHighConfidenceAnalysis($overSegmented))->toBeFalse()
        ->and($detector->isHighConfidenceAnalysis($tinyRegion))->toBeFalse();
});
