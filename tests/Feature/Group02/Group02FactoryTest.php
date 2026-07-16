<?php

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;

it('creates valid fictional records with relative demo paths only', function (): void {
    $mediaItem = MediaItem::factory()->create();
    $incoming = IncomingUpload::factory()->create();
    $version = MediaFileVersion::factory()->create();

    expect($mediaItem->archive_id)->toStartWith('FA-DEMO-')
        ->and($mediaItem->title)->toBe('Fictional Archive Scene')
        ->and($incoming->upload_id)->toStartWith('UP-DEMO-')
        ->and($incoming->original_filename)->toStartWith('fictional-upload-')
        ->and($incoming->incoming_path)->toStartWith('demo/')
        ->and($version->storage_path)->toStartWith('demo/')
        ->and($incoming->incoming_path)->not->toContain('\\')
        ->and($version->storage_path)->not->toContain('\\')
        ->and($incoming->incoming_path)->not->toMatch('/^[A-Za-z]:/')
        ->and($version->storage_path)->not->toMatch('/^[A-Za-z]:/');
});

it('casts every enum-backed field to its enum class', function (): void {
    $mediaItem = MediaItem::factory()->create();
    $incoming = IncomingUpload::factory()->create();
    $version = MediaFileVersion::factory()->create();

    expect($mediaItem->media_type)->toBeInstanceOf(UnitEnum::class)
        ->and($mediaItem->date_confidence)->toBeInstanceOf(UnitEnum::class)
        ->and($mediaItem->visibility)->toBeInstanceOf(UnitEnum::class)
        ->and($mediaItem->review_status)->toBeInstanceOf(UnitEnum::class)
        ->and($mediaItem->sensitivity_status)->toBeInstanceOf(UnitEnum::class)
        ->and($incoming->processing_status)->toBeInstanceOf(UnitEnum::class)
        ->and($incoming->review_status)->toBeInstanceOf(UnitEnum::class)
        ->and($incoming->duplicate_status)->toBeInstanceOf(UnitEnum::class)
        ->and($version->version_type)->toBeInstanceOf(UnitEnum::class)
        ->and($version->generation_status)->toBeInstanceOf(UnitEnum::class);
});
