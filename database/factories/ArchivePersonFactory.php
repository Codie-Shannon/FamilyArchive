<?php

namespace Database\Factories;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArchivePerson> */
final class ArchivePersonFactory extends Factory
{
    protected $model = ArchivePerson::class;

    public function definition(): array
    {
        return [
            'person_id' => 'PER-'.Str::upper((string) Str::ulid()),
            'display_name' => 'Mara Example',
            'alternate_names' => ['M. Example'],
            'name_certainty' => PersonNameCertainty::Probable,
            'birth_on' => null,
            'birth_year' => 1912,
            'birth_decade' => null,
            'birth_precision' => PersonDatePrecision::YearOnly,
            'death_on' => null,
            'death_year' => null,
            'death_decade' => 1980,
            'death_precision' => PersonDatePrecision::DecadeOnly,
            'life_state' => 'deceased',
            'identity_state' => 'confirmed',
            'fact_confidence' => StructuredDateConfidence::Medium,
            'source_note' => 'Synthetic register and album annotations.',
            'is_private' => false,
            'family_branch_id' => FamilyBranch::factory(),
            'merged_into_id' => null,
            'notes' => 'Synthetic Group 14 person for automated testing.',
            'review_state' => KnowledgeReviewState::Accepted,
            'review_reason' => 'Accept fictional person for automated testing.',
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
