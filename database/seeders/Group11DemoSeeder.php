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
            $payload = $this->payload($item);
            $payload['title'] = 'Fictional Garden Gathering';
            $payload['description'] = 'First accepted fictional description.';
            $payload['change_reason'] = 'Correct the fictional event title.';
            app(UpdatePhotoMetadata::class)->handle($item, $owner, $payload);
            $item->refresh();
        } if ($item->metadata_revision === 1) {
            $payload = $this->payload($item);
            $payload['description'] = 'Second accepted fictional description for audit proof.';
            $payload['story'] = 'Expanded sanitized fictional story.';
            $payload['change_reason'] = 'Add approved fictional context.';
            app(UpdatePhotoMetadata::class)->handle($item, $owner, $payload);
        }
    }

    /**
     * @return array{
     *     title: ?string,
     *     description: ?string,
     *     story: ?string,
     *     date_precision: string,
     *     canonical_date: ?string,
     *     date_year: ?int,
     *     estimated_decade: ?int,
     *     structured_date_confidence: string,
     *     date_review_state: string,
     *     date_source_note: ?string,
     *     date_reason: ?string,
     *     change_reason: string,
     *     expected_metadata_revision: int
     * }
     */
    private function payload(MediaItem $item): array
    {
        return [
            'title' => $item->title,
            'description' => $item->description,
            'story' => $item->story,
            'date_precision' => $item->date_precision->value,
            'canonical_date' => $item->canonical_date?->format('Y-m-d'),
            'date_year' => $item->date_year,
            'estimated_decade' => $item->estimated_decade,
            'structured_date_confidence' => $item->structured_date_confidence->value,
            'date_review_state' => $item->date_review_state->value,
            'date_source_note' => $item->date_source_note,
            'date_reason' => $item->date_reason,
            'change_reason' => 'Record fictional metadata.',
            'expected_metadata_revision' => (int) $item->metadata_revision,
        ];
    }
}
