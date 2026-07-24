<?php

namespace App\Domain\Archive\Actions;

use App\Domain\Archive\Contracts\NoOverwriteOriginalWriter;
use App\Domain\Archive\Exceptions\ArchivePromotionException;
use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Archive\ValueObjects\WrittenOriginalObject;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class PromoteIncomingPhoto
{
    /** @var list<DuplicateStatus> */
    private const ELIGIBLE_DUPLICATE_STATUSES = [
        DuplicateStatus::NoMatch,
        DuplicateStatus::NotDuplicate,
        DuplicateStatus::RelatedButDistinct,
    ];

    public function __construct(
        private ArchiveIdGenerator $archiveIds,
        private ArchiveStoragePath $paths,
        private NoOverwriteOriginalWriter $writer,
    ) {}

    public function handle(IncomingUpload $upload, User $actor): ArchivePromotion
    {
        if ($actor->role !== 'owner' || $actor->email_verified_at === null) {
            throw new ArchivePromotionException('Only a verified Owner may promote an archive original.');
        }

        $existing = ArchivePromotion::query()
            ->where('incoming_upload_id', $upload->id)
            ->first();

        if ($existing !== null) {
            return $existing->load(['incomingUpload', 'mediaItem', 'originalVersion', 'actor']);
        }

        $snapshot = DB::transaction(function () use ($upload): IncomingUpload {
            $locked = IncomingUpload::query()->lockForUpdate()->findOrFail($upload->id);
            $this->assertDatabaseEligibility($locked);

            return $locked;
        });

        $this->assertQuarantineIntegrity($snapshot);

        $archiveId = $this->archiveIds->allocate(MediaType::Photo);
        $extension = (string) $snapshot->extension;
        $target = $this->paths->original(MediaType::Photo, $archiveId, $extension);
        $written = null;
        $databaseCommitted = false;

        try {
            $written = $this->writer->copyFromQuarantine(
                (string) $snapshot->incoming_path,
                $target['path'],
                $snapshot->file_size_bytes,
                (string) $snapshot->sha256,
            );

            $promotion = DB::transaction(function () use ($upload, $actor, $archiveId, $target, $written): ArchivePromotion {
                $locked = IncomingUpload::query()->lockForUpdate()->findOrFail($upload->id);

                $existing = ArchivePromotion::query()
                    ->where('incoming_upload_id', $locked->id)
                    ->first();

                if ($existing !== null) {
                    throw new ArchivePromotionException('This retained upload was promoted concurrently.');
                }

                $this->assertDatabaseEligibility($locked);
                $this->assertQuarantineIntegrity($locked);

                $mediaItem = MediaItem::query()->create([
                    'archive_id' => $archiveId,
                    'media_type' => MediaType::Photo,
                    'title' => null,
                    'description' => null,
                    'story' => null,
                    'canonical_date' => null,
                    'estimated_decade' => null,
                    'date_confidence' => DateConfidence::Unknown,
                    'visibility' => MediaVisibility::PrivateArchive,
                    'review_status' => MediaReviewStatus::Approved,
                    'sensitivity_status' => SensitivityStatus::NotFlagged,
                    'created_by' => $actor->id,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ]);

                $original = MediaFileVersion::query()->create([
                    'media_item_id' => $mediaItem->id,
                    'parent_version_id' => null,
                    'version_type' => MediaFileVersionType::Original,
                    'storage_disk' => $target['disk']->value,
                    'storage_path' => $target['path'],
                    'mime_type' => $locked->mime_type,
                    'extension' => $locked->extension,
                    'file_size_bytes' => $written->storedBytes,
                    'width' => $locked->width,
                    'height' => $locked->height,
                    'duration_ms' => null,
                    'sha256' => $written->storedSha256,
                    'perceptual_hash' => null,
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => null,
                    'is_preferred' => true,
                ]);

                $promotion = ArchivePromotion::query()->create([
                    'incoming_upload_id' => $locked->id,
                    'media_item_id' => $mediaItem->id,
                    'original_media_file_version_id' => $original->id,
                    'actor_id' => $actor->id,
                    'source_disk' => 'archive_quarantine',
                    'source_path' => (string) $locked->incoming_path,
                    'target_disk' => $target['disk']->value,
                    'target_path' => $target['path'],
                    'source_bytes' => $locked->file_size_bytes,
                    'target_bytes' => $written->storedBytes,
                    'source_sha256' => (string) $locked->sha256,
                    'target_sha256' => $written->storedSha256,
                    'promoted_at' => now(),
                ]);

                $locked->forceFill([
                    'reviewed_by' => $actor->id,
                    'media_item_id' => $mediaItem->id,
                    'processing_status' => IncomingProcessingStatus::Processed,
                    'review_status' => IncomingReviewStatus::Approved,
                    'reviewed_at' => now(),
                ])->save();

                return $promotion;
            }, 5);

            $databaseCommitted = true;

            return $promotion->load(['incomingUpload', 'mediaItem', 'originalVersion', 'actor']);
        } catch (Throwable $exception) {
            if (! $databaseCommitted && $written instanceof WrittenOriginalObject) {
                $this->writer->removeCreated($written);
            }

            throw $exception;
        }
    }

    public function isEligible(IncomingUpload $upload): bool
    {
        try {
            $this->assertDatabaseEligibility($upload);
            $this->assertQuarantineIntegrity($upload);

            return true;
        } catch (ArchivePromotionException) {
            return false;
        }
    }

    private function assertDatabaseEligibility(IncomingUpload $upload): void
    {
        if ($upload->media_type !== MediaType::Photo) {
            throw new ArchivePromotionException('Only retained photo intake records are eligible in Group 08.');
        }

        if (
            ! $upload->source_file_retained
            || $upload->retained_at === null
            || $upload->source_file_removed_at !== null
            || ! is_string($upload->incoming_path)
            || $upload->incoming_path === ''
        ) {
            throw new ArchivePromotionException('The retained quarantine source is not eligible for promotion.');
        }

        if (
            ! is_string($upload->sha256)
            || ! preg_match('/^[a-f0-9]{64}$/', strtolower($upload->sha256))
            || $upload->file_size_bytes < 1
            || ! is_string($upload->extension)
            || $upload->extension === ''
            || ! str_starts_with($upload->mime_type, 'image/')
            || $upload->width === null
            || $upload->height === null
            || $upload->width < 1
            || $upload->height < 1
        ) {
            throw new ArchivePromotionException('Validated photo integrity facts are incomplete.');
        }

        if (! in_array($upload->duplicate_status, self::ELIGIBLE_DUPLICATE_STATUSES, true)) {
            throw new ArchivePromotionException('The duplicate-review outcome is not eligible for archive acceptance.');
        }

        if ($upload->media_item_id !== null) {
            throw new ArchivePromotionException('This IncomingUpload is already linked to a MediaItem.');
        }

        if (ArchivePromotion::query()->where('incoming_upload_id', $upload->id)->exists()) {
            throw new ArchivePromotionException('This IncomingUpload already has a promotion audit record.');
        }
    }

    private function assertQuarantineIntegrity(IncomingUpload $upload): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_quarantine');
        $path = (string) $upload->incoming_path;

        if (! $disk->exists($path)) {
            throw new ArchivePromotionException('The retained quarantine source does not exist.');
        }

        $size = $disk->size($path);
        $sha256 = $disk->checksum($path, ['checksum_algo' => 'sha256']);

        if (
            $size !== $upload->file_size_bytes
            || ! is_string($sha256)
            || ! hash_equals(strtolower((string) $upload->sha256), strtolower($sha256))
        ) {
            throw new ArchivePromotionException('The retained quarantine source does not match its database integrity facts.');
        }
    }
}
