<?php

namespace App\Domain\Intake\Services;

use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\ValueObjects\SanitizedUploadFilename;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class CreateIncomingPhotoRecord
{
    public function __construct(private PhotoIntakeValidator $validator, private IncomingUploadIdGenerator $ids, private ArchiveStoragePath $paths) {}

    public function create(User $owner, UploadedFile $file): IncomingUpload
    {
        $facts = $this->validator->validate($file);

        return DB::transaction(function () use ($owner, $facts): IncomingUpload {
            $id = $this->ids->generate();
            $name = (string) new SanitizedUploadFilename($facts->originalFilename, $facts->extension);
            $planned = $this->paths->quarantine('incoming', $id, $name);

            return IncomingUpload::query()->create(['upload_id' => $id, 'uploader_id' => $owner->id, 'reviewed_by' => null, 'media_item_id' => null, 'original_filename' => $facts->originalFilename, 'incoming_path' => $planned['path'], 'mime_type' => $facts->mimeType, 'extension' => $facts->extension, 'file_size_bytes' => $facts->sizeBytes, 'width' => $facts->width, 'height' => $facts->height, 'duration_ms' => null, 'sha256' => null, 'perceptual_hash' => null, 'processing_status' => IncomingProcessingStatus::Pending, 'review_status' => IncomingReviewStatus::PendingReview, 'duplicate_status' => DuplicateStatus::NotChecked, 'source_file_retained' => false, 'source_file_removed_at' => null, 'submitted_at' => now(), 'reviewed_at' => null]);
        });
    }
}
