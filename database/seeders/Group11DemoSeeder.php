<?php

namespace Database\Seeders;

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Actions\UpdatePhotoMetadata;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class Group11DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (MediaItem::query()->whereNotLike('archive_id', 'G11-DEMO-%')->exists()) {
            throw new RuntimeException('Group 11 demo seeding refuses to mix with non-demo archive records.');
        } $owner = User::query()->firstOrCreate(['email' => 'group11-owner@example.test'], ['name' => 'Fictional Archive Owner', 'password' => bcrypt('Group11Demo!2026'), 'role' => 'owner', 'email_verified_at' => now()]);
        $item = MediaItem::query()->firstOrCreate(['archive_id' => 'G11-DEMO-001'], ['media_type' => MediaType::Photo, 'title' => 'Fictional Garden Afternoon', 'description' => 'A sanitized fictional description.', 'story' => 'A generated fictional story.', 'date_confidence' => DateConfidence::Estimated, 'visibility' => MediaVisibility::PrivateArchive, 'review_status' => MediaReviewStatus::Approved, 'sensitivity_status' => SensitivityStatus::NotFlagged, 'created_by' => $owner->id, 'approved_by' => $owner->id, 'approved_at' => now(), 'metadata_revision' => 0]);
        if ($item->metadata_revision === 0) {
            app(UpdatePhotoMetadata::class)->handle($item, $owner, ['title' => 'Fictional Garden Gathering', 'description' => 'First accepted fictional description.', 'story' => $item->story, 'change_reason' => 'Correct the fictional event title.', 'expected_metadata_revision' => 0]);
            $item->refresh();
        } if ($item->metadata_revision === 1) {
            app(UpdatePhotoMetadata::class)->handle($item, $owner, ['title' => $item->title, 'description' => 'Second accepted fictional description for audit proof.', 'story' => 'Expanded sanitized fictional story.', 'change_reason' => 'Add approved fictional context.', 'expected_metadata_revision' => 1]);
        }
    }
}
