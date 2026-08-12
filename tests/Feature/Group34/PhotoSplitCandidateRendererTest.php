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
        ->and($rendered->recipe['quality_signals']['checks'])->toContain('minimum_region_size', 'detail_or_blur_proxy', 'rotation_clipping_guard')
        ->and($rendered->recipe['quality_signals']['status'])->toBeIn(['attention', 'automatic_checks_passed_visual_review_required'])
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

it('renders a verified staged source without requiring an in-memory source handoff', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'familyarchive-render-file-test-');
    expect($path)->toBeString();
    file_put_contents($path, splitRendererFixture());

    try {
        $rendered = app(PhotoSplitCandidateRenderer::class)->renderFile($path, 80, 60, 120, 80, -1.0);

        expect($rendered->recipe['manual_rotation_degrees_clockwise'])->toBe(-1.0)
            ->and(getimagesizefromstring($rendered->bytes)['mime'])->toBe('image/webp');
    } finally {
        @unlink($path);
    }
});

it('streams oversized split crops through the sharp renderer', function (): void {
    $node = PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\nodejs\\node.exe' : '/usr/local/bin/node';
    $imageMagick = (string) config('archive.multi_photo.candidate_rendering.imagemagick_path');
    if (! is_file($node) || ! is_file($imageMagick) || ! is_file(base_path('node_modules/sharp/package.json'))) {
        $this->markTestSkipped('The Sharp and ImageMagick streaming runtimes are unavailable.');
    }
    config()->set('archive.multi_photo.candidate_rendering.sharp_node_path', $node);
    config()->set('archive.multi_photo.candidate_rendering.sharp_minimum_source_pixels', 1);
    config()->set('archive.multi_photo.candidate_rendering.sharp_max_output_pixels', 5000);
    config()->set('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 1.0);

    $rendered = app(PhotoSplitCandidateRenderer::class)->render(splitRendererFixture(), 80, 60, 120, 80, 90.0);

    expect($rendered->recipe['pipeline_version'])->toBe(12)
        ->and($rendered->recipe['rendering_backend'])->toBe('imagemagick_stream_bilinear_sharp_v7')
        ->and($rendered->height)->toBeGreaterThan($rendered->width)
        ->and($rendered->width * $rendered->height)->toBeLessThanOrEqual(5000)
        ->and($rendered->recipe['output_scaling']['scale'])->toBeLessThan(1)
        ->and($rendered->recipe['manual_rotation_degrees_clockwise'])->toBe(90.0)
        ->and(getimagesizefromstring($rendered->bytes)['mime'])->toBe('image/webp');
});

it('streams a staged oversized source through the external renderer', function (): void {
    $node = PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\nodejs\\node.exe' : '/usr/local/bin/node';
    $imageMagick = (string) config('archive.multi_photo.candidate_rendering.imagemagick_path');
    if (! is_file($node) || ! is_file($imageMagick) || ! is_file(base_path('node_modules/sharp/package.json'))) {
        $this->markTestSkipped('The Sharp and ImageMagick streaming runtimes are unavailable.');
    }
    config()->set('archive.multi_photo.candidate_rendering.sharp_node_path', $node);
    config()->set('archive.multi_photo.candidate_rendering.sharp_minimum_source_pixels', 1);
    config()->set('archive.multi_photo.candidate_rendering.sharp_max_output_pixels', 5000);
    config()->set('archive.multi_photo.candidate_rendering.minimum_deskew_confidence', 1.0);
    $path = tempnam(sys_get_temp_dir(), 'familyarchive-stream-file-test-');
    expect($path)->toBeString();
    file_put_contents($path, splitRendererFixture());

    try {
        $rendered = app(PhotoSplitCandidateRenderer::class)->renderFile($path, 80, 60, 120, 80, 90.0);

        expect($rendered->recipe['rendering_backend'])->toBe('imagemagick_stream_bilinear_sharp_v7')
            ->and($rendered->width * $rendered->height)->toBeLessThanOrEqual(5000)
            ->and(getimagesizefromstring($rendered->bytes)['mime'])->toBe('image/webp');
    } finally {
        @unlink($path);
    }
});
