<?php

namespace Database\Factories;

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncomingUpload>
 */
class IncomingUploadFactory extends Factory
{
    protected $model = IncomingUpload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::lower(Str::random(12));

        return [
            'upload_id' => 'UP-DEMO-'.Str::upper($token),
            'uploader_id' => User::factory()->state([
                'name' => 'Fictional Archive Contributor',
                'email' => 'archive-contributor-'.$token.'@example.test',
                'role' => 'viewer',
            ]),
            'reviewed_by' => null,
            'media_item_id' => null,
            'media_type' => MediaType::Photo,
            'original_filename' => 'fictional-upload-'.$token.'.jpg',
            'incoming_path' => 'demo/intake/fictional-upload-'.$token.'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 4096,
            'width' => 1600,
            'height' => 900,
            'duration_ms' => null,
            'sha256' => hash('sha256', 'fictional-upload-'.$token),
            'perceptual_hash' => 'demo-'.$token,
            'processing_status' => IncomingProcessingStatus::Pending,
            'review_status' => IncomingReviewStatus::PendingReview,
            'duplicate_status' => DuplicateStatus::NotChecked,
            'source_file_retained' => true,
            'retained_at' => now(),
            'source_file_removed_at' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ];
    }
}
