<?php

namespace Database\Seeders;

use App\Domain\Knowledge\Actions\ReviewArchiveEvent;
use App\Domain\Knowledge\Actions\ReviewArchiveLocation;
use App\Domain\Knowledge\Actions\ReviewArchivePerson;
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

final class ScreenshotGroup18DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG18 dataset is local-only.');

        $owner = User::query()->where('email', 'sg12-owner@example.test')->firstOrFail();
        $branch = FamilyBranch::query()->where('branch_id', 'SG12-BRN-HARBOUR')->firstOrFail();

        $locations = [
            ['Fictional Harbour District', 'NZ', 'Auckland', 'Waitematā'],
            ['Fictional Hill Region', 'NZ', 'Wellington', 'Te Aro'],
            ['Fictional Southern Plains', 'NZ', 'Canterbury', 'Waimakariri'],
        ];

        foreach ($locations as [$label, $country, $region, $locality]) {
            if (ArchiveLocation::query()->where('label', $label)->exists()) {
                continue;
            }

            app(ReviewArchiveLocation::class)->create([
                'label' => $label,
                'country_code' => $country,
                'region' => $region,
                'locality' => $locality,
                'precision' => LocationPrecision::Locality->value,
                'is_sensitive' => false,
                'review_state' => KnowledgeReviewState::Accepted->value,
                'confidence' => StructuredDateConfidence::High->value,
                'source_note' => 'Fictional album annotation prepared for SG18 evidence.',
                'review_reason' => 'Accept this synthetic reviewed location.',
            ], $owner);
        }

        $events = [
            ['Harbour family picnic', EventType::Celebration, 1964, 'Fictional Harbour District'],
            ['Hill-region railway outing', EventType::Migration, 1972, 'Fictional Hill Region'],
            ['Southern branch reunion', EventType::Reunion, 1984, 'Fictional Southern Plains'],
        ];

        foreach ($events as [$name, $type, $year, $locationLabel]) {
            if (ArchiveEvent::query()->where('name', $name)->exists()) {
                continue;
            }

            $location = ArchiveLocation::query()->where('label', $locationLabel)->firstOrFail();
            app(ReviewArchiveEvent::class)->create([
                'name' => $name,
                'type' => $type->value,
                'starts_on' => null,
                'ends_on' => null,
                'date_precision' => DatePrecision::YearOnly->value,
                'date_year' => $year,
                'estimated_decade' => null,
                'date_confidence' => StructuredDateConfidence::High->value,
                'date_source_note' => 'Fictional album caption prepared for SG18 evidence.',
                'archive_location_id' => $location->id,
                'description' => 'A fictional reviewed event used only for archive navigation evidence.',
                'review_state' => KnowledgeReviewState::Accepted->value,
                'review_reason' => 'Accept this synthetic reviewed event.',
            ], $owner);
        }

        $people = [
            ['Aroha Raukawa', 1912, 1980],
            ['Mereana Raukawa', 1938, 2010],
            ['Wiremu Raukawa', 1941, 2020],
        ];

        foreach ($people as [$name, $birthYear, $deathDecade]) {
            if (ArchivePerson::query()->where('display_name', $name)->exists()) {
                continue;
            }

            app(ReviewArchivePerson::class)->create([
                'display_name' => $name,
                'alternate_names' => [],
                'name_certainty' => PersonNameCertainty::Confirmed->value,
                'birth_on' => null,
                'birth_year' => $birthYear,
                'birth_decade' => null,
                'birth_precision' => PersonDatePrecision::YearOnly->value,
                'death_on' => null,
                'death_year' => null,
                'death_decade' => $deathDecade,
                'death_precision' => PersonDatePrecision::DecadeOnly->value,
                'life_state' => 'deceased',
                'fact_confidence' => StructuredDateConfidence::Medium->value,
                'source_note' => 'Fictional family register prepared for SG18 evidence.',
                'is_private' => false,
                'family_branch_id' => $branch->id,
                'notes' => 'Synthetic reviewed identity used only for local evidence.',
                'review_state' => KnowledgeReviewState::Accepted->value,
                'review_reason' => 'Accept this synthetic reviewed identity.',
            ], $owner);
        }
    }
}
