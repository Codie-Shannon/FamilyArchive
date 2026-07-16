<?php

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;

it('connects uploader reviewer archive record and file lineage explicitly', function (): void {
    $uploader = User::factory()->create();
    $reviewer = User::factory()->create();
    $mediaItem = MediaItem::factory()->create([
        'created_by' => $reviewer->id,
        'approved_by' => $reviewer->id,
    ]);

    $incoming = IncomingUpload::factory()->create([
        'uploader_id' => $uploader->id,
        'reviewed_by' => $reviewer->id,
        'media_item_id' => $mediaItem->id,
    ]);

    $original = MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'version_type' => MediaFileVersionType::Original,
        'generation_status' => GenerationStatus::NotRequired,
    ]);

    $webDisplay = MediaFileVersion::factory()->create([
        'media_item_id' => $mediaItem->id,
        'parent_version_id' => $original->id,
        'version_type' => MediaFileVersionType::WebDisplay,
        'generation_status' => GenerationStatus::Ready,
        'is_preferred' => true,
    ]);

    expect($incoming->uploader->is($uploader))->toBeTrue()
        ->and($incoming->reviewer?->is($reviewer))->toBeTrue()
        ->and($incoming->mediaItem?->is($mediaItem))->toBeTrue()
        ->and($mediaItem->createdBy->is($reviewer))->toBeTrue()
        ->and($mediaItem->approvedBy?->is($reviewer))->toBeTrue()
        ->and($mediaItem->incomingUploads)->toHaveCount(1)
        ->and($mediaItem->fileVersions)->toHaveCount(2)
        ->and($webDisplay->parentVersion?->is($original))->toBeTrue()
        ->and($original->derivatives->first()?->is($webDisplay))->toBeTrue();
});

it('allows intake to exist without approval or an archive record', function (): void {
    $incoming = IncomingUpload::factory()->create([
        'media_item_id' => null,
        'reviewed_by' => null,
    ]);

    expect($incoming->media_item_id)->toBeNull()
        ->and($incoming->mediaItem)->toBeNull()
        ->and($incoming->reviewer)->toBeNull();
});
