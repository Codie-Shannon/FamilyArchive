<?php

namespace Database\Seeders;

use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

final class Group08DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'archive-owner@example.test'],
            [
                'name' => 'Archive Owner',
                'password' => bcrypt('group08-demo-only'),
                'role' => 'owner',
                'email_verified_at' => now(),
            ],
        );

        $this->seedUpload(
            $owner,
            'UP-G08-ELIGIBLE-001',
            'group-08/eligible/fictional-archive-photo.png',
            DuplicateStatus::NoMatch,
            $this->pngBytes(),
        );

        $this->seedUpload(
            $owner,
            'UP-G08-RELATED-001',
            'group-08/related/fictional-related-photo.png',
            DuplicateStatus::RelatedButDistinct,
            $this->pngBytes('related'),
        );

        $this->seedUpload(
            $owner,
            'UP-G08-BLOCKED-001',
            'group-08/blocked/fictional-confirmed-duplicate.png',
            DuplicateStatus::ConfirmedDuplicate,
            $this->pngBytes('blocked'),
        );
    }

    private function seedUpload(
        User $owner,
        string $uploadId,
        string $path,
        DuplicateStatus $duplicateStatus,
        string $bytes,
    ): void {
        if (IncomingUpload::query()->where('upload_id', $uploadId)->exists()) {
            return;
        }

        Storage::disk('archive_quarantine')->put($path, $bytes);

        IncomingUpload::query()->create([
            'upload_id' => $uploadId,
            'uploader_id' => $owner->id,
            'reviewed_by' => null,
            'media_item_id' => null,
            'media_type' => MediaType::Photo,
            'original_filename' => 'fictional-archive-photo.png',
            'incoming_path' => $path,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'file_size_bytes' => strlen($bytes),
            'width' => 2,
            'height' => 2,
            'duration_ms' => null,
            'sha256' => hash('sha256', $bytes),
            'perceptual_hash' => null,
            'processing_status' => IncomingProcessingStatus::Pending,
            'review_status' => IncomingReviewStatus::PendingReview,
            'duplicate_status' => $duplicateStatus,
            'source_file_retained' => true,
            'retained_at' => now(),
            'source_file_removed_at' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ]);
    }

    private function pngBytes(string $suffix = 'eligible'): string
    {
        $base = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR4nGP8z8DAwMDAxMDAwAAABQABDQottAAAAABJRU5ErkJggg==', true);

        return is_string($base) ? $base.$suffix : $suffix;
    }
}
