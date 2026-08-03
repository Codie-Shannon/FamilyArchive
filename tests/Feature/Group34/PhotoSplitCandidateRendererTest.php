<?php

use App\Domain\Processing\Services\PhotoSplitCandidateRenderer;

function splitRendererFixture(): string
{
    $image = imagecreatetruecolor(320, 220);
    $white = imagecolorallocate($image, 250, 250, 250);
    $black = imagecolorallocate($image, 10, 10, 10);
    $red = imagecolorallocate($image, 230, 20, 20);
    imagefill($image, 0, 0, $white);
    imagerectangle($image, 80, 60, 199, 139, $black);
    imagefilledrectangle($image, 80, 60, 88, 68, $red);
    imagefilledrectangle($image, 191, 131, 199, 139, $red);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return is_string($bytes) ? $bytes : '';
}

it('rotates a padded child before calculating its final crop', function (): void {
    config()->set('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 1.0);
    config()->set('archive.multi_photo.candidate_rendering.final_safety_pixels', 2);
    $source = splitRendererFixture();
    $sourceHash = hash('sha256', $source);

    $rendered = app(PhotoSplitCandidateRenderer::class)->render($source, 80, 60, 120, 80, 37.0);
    $radians = deg2rad(37);
    $minimumWidth = (int) ceil(abs(120 * cos($radians)) + abs(80 * sin($radians))) + 4;
    $minimumHeight = (int) ceil(abs(120 * sin($radians)) + abs(80 * cos($radians))) + 4;

    expect(hash('sha256', $source))->toBe($sourceHash)
        ->and($rendered->width)->toBeGreaterThanOrEqual($minimumWidth)
        ->and($rendered->height)->toBeGreaterThanOrEqual($minimumHeight)
        ->and($rendered->recipe['operation_order'])->toBe(['padded_extract', 'independent_rotate', 'final_edge_crop'])
        ->and($rendered->recipe['clipping_guard'])->toBe('rotate_before_final_crop')
        ->and($rendered->recipe['manual_rotation_degrees_clockwise'])->toBe(37.0)
        ->and(getimagesizefromstring($rendered->bytes)['mime'])->toBe('image/webp');
});

it('renders quarter-turn overrides independently for each split photo', function (): void {
    config()->set('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 1.0);
    $rendered = app(PhotoSplitCandidateRenderer::class)->render(splitRendererFixture(), 80, 60, 120, 80, 90.0);

    expect($rendered->height)->toBeGreaterThan($rendered->width)
        ->and($rendered->recipe['manual_rotation_degrees_clockwise'])->toBe(90.0)
        ->and($rendered->recipe['final_crop']['width'])->toBe($rendered->width)
        ->and($rendered->recipe['final_crop']['height'])->toBe($rendered->height);
});
