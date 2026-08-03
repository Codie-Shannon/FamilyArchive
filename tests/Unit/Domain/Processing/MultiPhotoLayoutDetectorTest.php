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
        ->and($analysis['method'])->toBe('continuous_seam_and_edge_discontinuity_v1');
});
