<?php

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('keeps direct file path columns off MediaItem', function (): void {
    $columns = Schema::getColumnListing('media_items');

    expect($columns)->not->toContain('original_path')
        ->and($columns)->not->toContain('web_path')
        ->and($columns)->not->toContain('thumbnail_path')
        ->and($columns)->not->toContain('storage_path')
        ->and($columns)->not->toContain('file_path');
});

it('stores original web display and thumbnail as distinct records', function (): void {
    $mediaItem = MediaItem::factory()->create();

    $original = MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'version_type' => MediaFileVersionType::Original,
        'generation_status' => GenerationStatus::NotRequired,
    ]);

    MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'parent_version_id' => $original->id,
        'version_type' => MediaFileVersionType::WebDisplay,
        'generation_status' => GenerationStatus::Ready,
    ]);

    MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'parent_version_id' => $original->id,
        'version_type' => MediaFileVersionType::Thumbnail,
        'generation_status' => GenerationStatus::Ready,
    ]);

    $versionTypes = $mediaItem->fileVersions()
        ->get()
        ->map(static fn (MediaFileVersion $version): string => $version->version_type->value)
        ->all();

    expect($versionTypes)
        ->toEqualCanonicalizing(['original', 'web_display', 'thumbnail']);
});

it('does not cascade-delete original versions through media or parent relationships', function (): void {
    $mediaItem = MediaItem::factory()->create();
    $original = MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'version_type' => MediaFileVersionType::Original,
    ]);
    $derivative = MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'parent_version_id' => $original->id,
        'version_type' => MediaFileVersionType::Thumbnail,
    ]);

    expect(fn () => $mediaItem->delete())->toThrow(QueryException::class);
    expect(fn () => $original->delete())->toThrow(QueryException::class);

    expect($mediaItem->fresh())->not->toBeNull()
        ->and($original->fresh())->not->toBeNull()
        ->and($derivative->fresh())->not->toBeNull();
});
