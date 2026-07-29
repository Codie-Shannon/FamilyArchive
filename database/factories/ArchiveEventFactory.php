<?php

namespace Database\Factories;

use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArchiveEvent> */
final class ArchiveEventFactory extends Factory
{
    protected $model = ArchiveEvent::class;

    public function definition(): array
    {
        return [
            'event_id' => 'EVT-'.Str::upper((string) Str::ulid()),
            'name' => 'Fictional family gathering',
            'type' => EventType::Reunion,
            'starts_on' => null,
            'ends_on' => null,
            'date_precision' => DatePrecision::YearOnly,
            'date_year' => 1978,
            'estimated_decade' => null,
            'date_confidence' => StructuredDateConfidence::Medium,
            'date_source_note' => 'Year inferred from a synthetic album caption.',
            'archive_location_id' => ArchiveLocation::factory(),
            'description' => 'Synthetic Group 13 event for automated testing.',
            'review_state' => KnowledgeReviewState::Accepted,
            'review_reason' => 'Accept fictional event for automated testing.',
            'created_by' => User::factory()->state([
                'role' => 'owner',
                'email_verified_at' => now(),
            ]),
            'reviewed_by' => fn (array $attributes) => $attributes['created_by'],
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ];
    }
}
