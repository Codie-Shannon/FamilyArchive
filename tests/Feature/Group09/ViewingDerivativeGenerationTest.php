<?php

use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function group09Png(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Unable to create Group 09 fixture.');
    }
    $background = imagecolorallocate($image, 40, 90, 140);
    imagefill($image, 0, 0, $background);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    if (! is_string($bytes)) {
        throw new RuntimeException('Unable to encode Group 09 fixture.');
    }

    return $bytes;
}

/** @return array{0: MediaItem, 1: MediaFileVersion, 2: User, 3: string} */
function group09ApprovedPhoto(int $width = 2400, int $height = 1200): array
{
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    $bytes = group09Png($width, $height);
    $path = 'photos/000/PH_000901.png';
    Storage::disk('archive_originals')->put($path, $bytes);

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $item = MediaItem::factory()->create([
        'archive_id' => 'PH_000901',
        'media_type' => MediaType::Photo,
        'review_status' => MediaReviewStatus::Approved,
        'approved_by' => $owner->id,
        'approved_at' => now(),
    ]);
    $original = MediaFileVersion::factory()->create([
        'media_item_id' => $item->id,
        'parent_version_id' => null,
        'version_type' => MediaFileVersionType::Original,
        'storage_disk' => 'archive_originals',
        'storage_path' => $path,
        'mime_type' => 'image/png',
        'extension' => 'png',
        'file_size_bytes' => strlen($bytes),
        'width' => $width,
        'height' => $height,
        'sha256' => hash('sha256', $bytes),
        'generation_status' => GenerationStatus::Ready,
        'generation_recipe' => null,
        'is_preferred' => true,
    ]);

    return [$item, $original, $owner, $bytes];
}

it('generates verified web-display and thumbnail WebP versions with lineage and leaves original untouched', function () {
    [$item, $original, $owner, $originalBytes] = group09ApprovedPhoto();
    $before = hash('sha256', $originalBytes);

    $result = app(GeneratePhotoViewingDerivatives::class)->handle($item, $owner);

    expect($result->webDisplay->version_type)->toBe(MediaFileVersionType::WebDisplay)
        ->and($result->thumbnail->version_type)->toBe(MediaFileVersionType::Thumbnail)
        ->and($result->webDisplay->parent_version_id)->toBe($original->id)
        ->and($result->thumbnail->parent_version_id)->toBe($original->id)
        ->and($result->webDisplay->storage_disk)->toBe('archive_derivatives')
        ->and($result->thumbnail->storage_disk)->toBe('archive_derivatives')
        ->and($result->webDisplay->mime_type)->toBe('image/webp')
        ->and($result->thumbnail->mime_type)->toBe('image/webp')
        ->and(max($result->webDisplay->width, $result->webDisplay->height))->toBeLessThanOrEqual(2000)
        ->and(max($result->thumbnail->width, $result->thumbnail->height))->toBeLessThanOrEqual(480)
        ->and($result->webDisplay->generation_recipe['recipe_version'])->toBe('photo-v1')
        ->and($result->thumbnail->generation_recipe['recipe_version'])->toBe('photo-v1')
        ->and($result->webDisplay->generation_recipe['quality'])->toBe(82)
        ->and($result->thumbnail->generation_recipe['quality'])->toBe(72)
        ->and(MediaFileVersion::query()->where('version_type', MediaFileVersionType::EditedFull)->count())->toBe(0)
        ->and(hash('sha256', Storage::disk('archive_originals')->get($original->storage_path)))->toBe($before);
});

it('never upscales small originals and is idempotent', function () {
    [$item, $original, $owner] = group09ApprovedPhoto(320, 200);
    $action = app(GeneratePhotoViewingDerivatives::class);

    $first = $action->handle($item, $owner);
    $second = $action->handle($item->fresh(), $owner);

    expect([$first->webDisplay->width, $first->webDisplay->height])->toBe([320, 200])
        ->and([$first->thumbnail->width, $first->thumbnail->height])->toBe([320, 200])
        ->and($second->createdWebDisplay)->toBeFalse()
        ->and($second->createdThumbnail)->toBeFalse()
        ->and(MediaFileVersion::query()->whereIn('version_type', ['web_display', 'thumbnail'])->count())->toBe(2)
        ->and(Storage::disk('archive_derivatives')->allFiles())->toHaveCount(2)
        ->and($first->webDisplay->parent_version_id)->toBe($original->id);
});

it('fails closed on source mismatch and preserves the original', function () {
    [$item, $original, $owner, $bytes] = group09ApprovedPhoto();
    $original->forceFill(['sha256' => hash('sha256', 'wrong')])->save();

    expect(fn () => app(GeneratePhotoViewingDerivatives::class)->handle($item, $owner))
        ->toThrow(DerivativeGenerationException::class)
        ->and(Storage::disk('archive_originals')->get($original->storage_path))->toBe($bytes)
        ->and(Storage::disk('archive_derivatives')->allFiles())->toBe([]);
});

it('never overwrites a pre-existing derivative target', function () {
    [$item, $original, $owner] = group09ApprovedPhoto();
    $path = 'web-display/photos/000/PH_000901.webp';
    Storage::disk('archive_derivatives')->put($path, 'pre-existing');
    $before = hash('sha256', Storage::disk('archive_derivatives')->get($path));

    expect(fn () => app(GeneratePhotoViewingDerivatives::class)->handle($item, $owner))
        ->toThrow(DerivativeGenerationException::class)
        ->and(hash('sha256', Storage::disk('archive_derivatives')->get($path)))->toBe($before)
        ->and(MediaFileVersion::query()->whereIn('version_type', ['web_display', 'thumbnail'])->count())->toBe(0)
        ->and($original->fresh()->version_type)->toBe(MediaFileVersionType::Original);
});
