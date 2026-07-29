<?php

namespace Database\Factories;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArchiveLocation> */
final class ArchiveLocationFactory extends Factory
{
    protected $model = ArchiveLocation::class;

    public function definition(): array
    {
        return [
            'location_id' => 'LOC-'.Str::upper((string) Str::ulid()),
            'label' => 'Fictional Wellington location',
            'country_code' => 'NZ',
            'region' => 'Wellington',
            'locality' => 'Te Aro',
            'precision' => LocationPrecision::Locality,
            'is_sensitive' => false,
            'review_state' => KnowledgeReviewState::Accepted,
            'confidence' => StructuredDateConfidence::High,
            'source_note' => 'Synthetic Group 13 source evidence.',
            'review_reason' => 'Accept fictional location for automated testing.',
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
