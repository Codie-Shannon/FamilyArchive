<?php

namespace Database\Seeders;

use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup23DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG23 dataset is local-only.');

        $member = $this->user('sg23-member@example.test', 'Ria Harbour', 'viewer');
        $owner = $this->user('sg23-owner@example.test', 'Jordan Vale', 'owner');

        $harbour = FamilyBranch::query()->updateOrCreate(
            ['branch_id' => 'SG23-BRN-HARBOUR'],
            [
                'name' => 'Harbour Raukawa Branch',
                'description' => 'A fictional reviewed family branch linking three generations of archive knowledge.',
                'is_sensitive' => false,
                'review_state' => KnowledgeReviewState::Accepted,
                'confidence' => StructuredDateConfidence::High,
                'source_note' => 'Synthetic family register prepared for SG23 evidence.',
                'review_reason' => 'Accept this fictional branch for unified archive evidence.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ],
        );

        $protected = FamilyBranch::query()->updateOrCreate(
            ['branch_id' => 'SG23-BRN-PROTECTED'],
            [
                'name' => 'Protected Living Branch',
                'description' => 'A synthetic sensitive branch used to prove filtering.',
                'is_sensitive' => true,
                'review_state' => KnowledgeReviewState::Accepted,
                'confidence' => StructuredDateConfidence::Medium,
                'source_note' => 'Synthetic privacy-boundary evidence.',
                'review_reason' => 'Retain behind the archive administration boundary.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ],
        );

        $locations = [
            ['SG23-LOC-HARBOUR', 'Te Raukawa Harbour', 'Auckland', 'Waitematā'],
            ['SG23-LOC-HILLS', 'Kōwhai Hill Station', 'Wellington', 'Te Aro'],
            ['SG23-LOC-PLAINS', 'Southern Waimarie Plains', 'Canterbury', 'Waimakariri'],
        ];

        foreach ($locations as [$stableId, $label, $region, $locality]) {
            ArchiveLocation::query()->updateOrCreate(
                ['location_id' => $stableId],
                [
                    'label' => $label,
                    'country_code' => 'NZ',
                    'region' => $region,
                    'locality' => $locality,
                    'precision' => LocationPrecision::Locality,
                    'is_sensitive' => false,
                    'review_state' => KnowledgeReviewState::Accepted,
                    'confidence' => StructuredDateConfidence::High,
                    'source_note' => 'Synthetic album annotation prepared for SG23 evidence.',
                    'review_reason' => 'Accept this fictional location for archive discovery.',
                    'created_by' => $owner->id,
                    'reviewed_by' => $owner->id,
                    'reviewed_at' => now(),
                    'metadata_revision' => 1,
                ],
            );
        }

        ArchiveLocation::query()->updateOrCreate(
            ['location_id' => 'SG23-LOC-PRIVATE'],
            [
                'label' => 'Protected Home Address',
                'country_code' => 'NZ',
                'region' => 'Auckland',
                'locality' => 'Private locality',
                'precision' => LocationPrecision::Private,
                'is_sensitive' => true,
                'review_state' => KnowledgeReviewState::Accepted,
                'confidence' => StructuredDateConfidence::High,
                'source_note' => 'Synthetic sensitive-location evidence.',
                'review_reason' => 'Keep exact household location out of member discovery.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ],
        );

        $people = [
            ['SG23-PER-AROHA', 'Hana Raukawa', 1912, 1980],
            ['SG23-PER-MEREANA', 'Pita Raukawa', 1938, 2010],
            ['SG23-PER-WIREMU', 'Rui Raukawa', 1941, 2020],
        ];

        foreach ($people as [$stableId, $name, $birthYear, $deathDecade]) {
            ArchivePerson::query()->updateOrCreate(
                ['person_id' => $stableId],
                [
                    'display_name' => $name,
                    'alternate_names' => [],
                    'name_certainty' => PersonNameCertainty::Confirmed,
                    'birth_on' => null,
                    'birth_year' => $birthYear,
                    'birth_decade' => null,
                    'birth_precision' => PersonDatePrecision::YearOnly,
                    'death_on' => null,
                    'death_year' => null,
                    'death_decade' => $deathDecade,
                    'death_precision' => PersonDatePrecision::DecadeOnly,
                    'life_state' => 'deceased',
                    'identity_state' => 'confirmed',
                    'fact_confidence' => StructuredDateConfidence::High,
                    'source_note' => 'Synthetic reviewed register prepared for SG23 evidence.',
                    'is_private' => false,
                    'family_branch_id' => $harbour->id,
                    'notes' => 'A fictional reviewed identity used only for local evidence.',
                    'review_state' => KnowledgeReviewState::Accepted,
                    'review_reason' => 'Accept this fictional identity for archive discovery.',
                    'created_by' => $owner->id,
                    'reviewed_by' => $owner->id,
                    'reviewed_at' => now(),
                    'metadata_revision' => 1,
                ],
            );
        }

        ArchivePerson::query()->updateOrCreate(
            ['person_id' => 'SG23-PER-LIVING'],
            [
                'display_name' => 'Protected Living Relative',
                'alternate_names' => [],
                'name_certainty' => PersonNameCertainty::Confirmed,
                'birth_on' => null,
                'birth_year' => 1992,
                'birth_decade' => null,
                'birth_precision' => PersonDatePrecision::YearOnly,
                'death_on' => null,
                'death_year' => null,
                'death_decade' => null,
                'death_precision' => PersonDatePrecision::Unknown,
                'life_state' => 'living',
                'identity_state' => 'confirmed',
                'fact_confidence' => StructuredDateConfidence::High,
                'source_note' => 'Synthetic privacy-filter evidence.',
                'is_private' => true,
                'family_branch_id' => $protected->id,
                'notes' => 'Withheld from ordinary archive discovery.',
                'review_state' => KnowledgeReviewState::Accepted,
                'review_reason' => 'Retain behind the archive administration boundary.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ],
        );

        $events = [
            ['SG23-EVT-PICNIC', 'Raukawa whānau picnic', EventType::Celebration, 1964, 'SG23-LOC-HARBOUR'],
            ['SG23-EVT-RAIL', 'Kōwhai railway homecoming', EventType::Migration, 1972, 'SG23-LOC-HILLS'],
            ['SG23-EVT-REUNION', 'Southern plains reunion', EventType::Reunion, 1984, 'SG23-LOC-PLAINS'],
        ];

        foreach ($events as [$stableId, $name, $type, $year, $locationId]) {
            $location = ArchiveLocation::query()->where('location_id', $locationId)->firstOrFail();
            ArchiveEvent::query()->updateOrCreate(
                ['event_id' => $stableId],
                [
                    'name' => $name,
                    'type' => $type,
                    'starts_on' => null,
                    'ends_on' => null,
                    'date_precision' => DatePrecision::YearOnly,
                    'date_year' => $year,
                    'estimated_decade' => null,
                    'date_confidence' => StructuredDateConfidence::High,
                    'date_source_note' => 'Synthetic album caption prepared for SG23 evidence.',
                    'archive_location_id' => $location->id,
                    'description' => 'A fictional reviewed event used only for unified archive evidence.',
                    'review_state' => KnowledgeReviewState::Accepted,
                    'review_reason' => 'Accept this fictional event for archive discovery.',
                    'created_by' => $owner->id,
                    'reviewed_by' => $owner->id,
                    'reviewed_at' => now(),
                    'metadata_revision' => 1,
                ],
            );
        }

        $member->forceFill(['family_connection' => 'Approved fictional family member'])->save();
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG23Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG23 evidence identity',
        ])->save();

        return $user;
    }
}
