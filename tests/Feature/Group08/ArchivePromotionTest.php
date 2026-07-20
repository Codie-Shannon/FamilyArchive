<?php

use App\Domain\Archive\Actions\PromoteIncomingPhoto;
use App\Domain\Archive\Exceptions\ArchivePromotionException;
use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function group08Upload(
    string $suffix = 'eligible',
    DuplicateStatus $duplicateStatus = DuplicateStatus::NoMatch,
    ?string $bytes = null,
): IncomingUpload {
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');

    $bytes ??= 'fictional-group-08-photo-'.$suffix;
    $path = 'group-08/'.$suffix.'/fictional-photo.jpg';
    Storage::disk('archive_quarantine')->put($path, $bytes);

    return IncomingUpload::factory()->create([
        'upload_id' => 'UP-G08-'.strtoupper(substr(hash('sha256', $suffix), 0, 12)),
        'media_type' => MediaType::Photo,
        'incoming_path' => $path,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'file_size_bytes' => strlen($bytes),
        'width' => 1600,
        'height' => 900,
        'sha256' => hash('sha256', $bytes),
        'perceptual_hash' => null,
        'processing_status' => IncomingProcessingStatus::Pending,
        'review_status' => IncomingReviewStatus::PendingReview,
        'duplicate_status' => $duplicateStatus,
        'source_file_retained' => true,
        'retained_at' => now(),
        'source_file_removed_at' => null,
        'media_item_id' => null,
    ]);
}

function group08Owner(): User
{
    return User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);
}

it('promotes one eligible retained photo with exact integrity and preserves quarantine', function () {
    $upload = group08Upload();
    $sourcePath = (string) $upload->incoming_path;
    $sourceBytes = Storage::disk('archive_quarantine')->get($sourcePath);
    $sourceHash = hash('sha256', $sourceBytes);

    $promotion = app(PromoteIncomingPhoto::class)->handle($upload, group08Owner());
    $fresh = $upload->fresh();
    $mediaItem = $promotion->mediaItem;
    $original = $promotion->originalVersion;

    expect($mediaItem->archive_id)->toBe('PH_000001')
        ->and($mediaItem->media_type)->toBe(MediaType::Photo)
        ->and($mediaItem->review_status)->toBe(MediaReviewStatus::Approved)
        ->and($original->version_type)->toBe(MediaFileVersionType::Original)
        ->and($original->storage_disk)->toBe('archive_originals')
        ->and($original->generation_status)->toBe(GenerationStatus::Ready)
        ->and($original->is_preferred)->toBeTrue()
        ->and($original->parent_version_id)->toBeNull()
        ->and($original->file_size_bytes)->toBe(strlen($sourceBytes))
        ->and($original->sha256)->toBe($sourceHash)
        ->and($fresh->media_item_id)->toBe($mediaItem->id)
        ->and($fresh->review_status)->toBe(IncomingReviewStatus::Approved)
        ->and($fresh->processing_status)->toBe(IncomingProcessingStatus::Processed)
        ->and($fresh->source_file_retained)->toBeTrue()
        ->and($fresh->incoming_path)->toBe($sourcePath)
        ->and(Storage::disk('archive_quarantine')->get($sourcePath))->toBe($sourceBytes)
        ->and(Storage::disk('archive_originals')->get($original->storage_path))->toBe($sourceBytes)
        ->and(MediaFileVersion::query()->where('version_type', '!=', MediaFileVersionType::Original->value)->count())->toBe(0);

    expect(array_key_exists('storage_path', $mediaItem->getAttributes()))->toBeFalse()
        ->and(array_key_exists('storage_disk', $mediaItem->getAttributes()))->toBeFalse()
        ->and(array_key_exists('sha256', $mediaItem->getAttributes()))->toBeFalse();
});

it('fails closed for blocked duplicate states and invalid retained facts', function (DuplicateStatus $status) {
    $upload = group08Upload($status->value, $status);

    expect(fn () => app(PromoteIncomingPhoto::class)->handle($upload, group08Owner()))
        ->toThrow(ArchivePromotionException::class);

    expect(MediaItem::count())->toBe(0)
        ->and(MediaFileVersion::count())->toBe(0)
        ->and(ArchivePromotion::count())->toBe(0);
})->with([
    DuplicateStatus::PossibleDuplicate,
    DuplicateStatus::ConfirmedDuplicate,
    DuplicateStatus::AlternateSource,
]);

it('fails closed when the source hash or retained state does not match', function () {
    $upload = group08Upload('mismatch');
    $upload->forceFill(['sha256' => hash('sha256', 'wrong')])->save();

    expect(fn () => app(PromoteIncomingPhoto::class)->handle($upload, group08Owner()))
        ->toThrow(ArchivePromotionException::class);

    expect(Storage::disk('archive_originals')->allFiles())->toBe([]);
});

it('never overwrites a pre-existing original target', function () {
    $upload = group08Upload('collision');
    $target = app(ArchiveStoragePath::class)->original(MediaType::Photo, 'PH_000001', 'jpg');
    Storage::disk('archive_originals')->put($target['path'], 'pre-existing-original');
    $beforeHash = hash('sha256', Storage::disk('archive_originals')->get($target['path']));

    expect(fn () => app(PromoteIncomingPhoto::class)->handle($upload, group08Owner()))
        ->toThrow(ArchivePromotionException::class);

    expect(hash('sha256', Storage::disk('archive_originals')->get($target['path'])))->toBe($beforeHash)
        ->and(MediaItem::count())->toBe(0)
        ->and(MediaFileVersion::count())->toBe(0)
        ->and(ArchivePromotion::count())->toBe(0);
});

it('removes only newly written rollback residue and never reuses the allocated archive id', function () {
    $first = group08Upload('rollback-one');
    $owner = group08Owner();

    MediaItem::creating(static function (): never {
        throw new RuntimeException('forced Group 08 database failure');
    });

    try {
        expect(fn () => app(PromoteIncomingPhoto::class)->handle($first, $owner))
            ->toThrow(RuntimeException::class);
    } finally {
        MediaItem::flushEventListeners();
    }

    expect(Storage::disk('archive_originals')->allFiles())->toBe([])
        ->and(Storage::disk('archive_quarantine')->exists((string) $first->incoming_path))->toBeTrue()
        ->and(MediaItem::count())->toBe(0)
        ->and(MediaFileVersion::count())->toBe(0)
        ->and(ArchivePromotion::count())->toBe(0);

    $secondBytes = 'fictional-group-08-photo-rollback-two';
    $secondPath = 'group-08/rollback-two/fictional-photo.jpg';
    Storage::disk('archive_quarantine')->put($secondPath, $secondBytes);
    $second = IncomingUpload::factory()->create([
        'upload_id' => 'UP-G08-ROLLBACK-TWO',
        'media_type' => MediaType::Photo,
        'incoming_path' => $secondPath,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'file_size_bytes' => strlen($secondBytes),
        'width' => 1600,
        'height' => 900,
        'sha256' => hash('sha256', $secondBytes),
        'duplicate_status' => DuplicateStatus::NotDuplicate,
        'source_file_retained' => true,
        'retained_at' => now(),
        'media_item_id' => null,
    ]);

    $promotion = app(PromoteIncomingPhoto::class)->handle($second, $owner);

    expect($promotion->mediaItem->archive_id)->toBe('PH_000002');
});

it('is idempotent after successful promotion', function () {
    $upload = group08Upload('idempotent');
    $action = app(PromoteIncomingPhoto::class);
    $owner = group08Owner();

    $first = $action->handle($upload, $owner);
    $second = $action->handle($upload->fresh(), $owner);

    expect($second->id)->toBe($first->id)
        ->and(MediaItem::count())->toBe(1)
        ->and(MediaFileVersion::count())->toBe(1)
        ->and(ArchivePromotion::count())->toBe(1)
        ->and(Storage::disk('archive_originals')->allFiles())->toHaveCount(1);
});

it('keeps promotion audit records immutable', function () {
    $promotion = app(PromoteIncomingPhoto::class)->handle(group08Upload('immutable'), group08Owner());

    expect(fn () => $promotion->update(['target_bytes' => 1]))->toThrow(LogicException::class);
    expect(fn () => $promotion->delete())->toThrow(LogicException::class);
});
