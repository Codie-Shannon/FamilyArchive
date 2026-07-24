<?php

namespace Database\Factories;

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\DateReviewState;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaItem>
 */
class MediaItemFactory extends Factory
{
    protected $model = MediaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::upper(Str::random(12));

        return [
            'archive_id' => 'FA-DEMO-'.$token,
            'media_type' => MediaType::Photo,
            'title' => 'Fictional Archive Scene',
            'description' => 'Sanitized metadata created only for schema validation.',
            'story' => 'A fictional archive story with no real people, places or media.',
            'canonical_date' => null,
            'date_precision' => DatePrecision::DecadeOnly,
            'date_year' => null,
            'estimated_decade' => 1980,
            'date_confidence' => DateConfidence::DecadeOnly,
            'structured_date_confidence' => StructuredDateConfidence::Low,
            'date_review_state' => DateReviewState::Accepted,
            'date_source_note' => 'Fictional annotation on a synthetic album sleeve.',
            'date_reason' => 'The fictional sleeve identifies the decade but not a year.',
            'visibility' => MediaVisibility::PrivateArchive,
            'review_status' => MediaReviewStatus::PendingReview,
            'sensitivity_status' => SensitivityStatus::NotFlagged,
            'created_by' => User::factory()->state([
                'name' => 'Fictional Archive Owner',
                'email' => 'archive-owner-'.Str::lower(Str::random(12)).'@example.test',
                'role' => 'owner',
            ]),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
