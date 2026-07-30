<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\ArchivePersonRevision;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewArchivePerson
{
    /** @param array<string, mixed> $input */
    public function create(array $input, User $actor): ArchivePerson
    {
        $values = $this->normalize($input);
        $this->assertValid($values);

        return DB::transaction(function () use ($values, $actor): ArchivePerson {
            $person = ArchivePerson::query()->create([
                ...$values,
                'person_id' => 'PER-'.Str::upper((string) Str::ulid()),
                'identity_state' => 'confirmed',
                'merged_into_id' => null,
                'created_by' => $actor->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ]);

            ArchivePersonRevision::createImmutable([
                'archive_person_id' => $person->id,
                'revision_number' => 1,
                'actor_user_id' => $actor->id,
                'from_revision' => 0,
                'to_revision' => 1,
                'changed_fields' => array_keys($values),
                'before_values' => [],
                'after_values' => $this->snapshot($person),
                'change_reason' => $values['review_reason'],
                'created_at' => now(),
            ]);

            return $person->fresh();
        });
    }

    /** @param array<string, mixed> $input */
    public function update(
        ArchivePerson $person,
        array $input,
        int $expectedRevision,
        User $actor
    ): ArchivePerson {
        $values = $this->normalize($input);
        $this->assertValid($values);

        return DB::transaction(function () use ($person, $values, $expectedRevision, $actor): ArchivePerson {
            $locked = ArchivePerson::query()->lockForUpdate()->findOrFail($person->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The person record changed after this form was opened.',
                ]);
            }

            $before = $this->snapshot($locked);
            $changed = [];

            foreach ($values as $field => $value) {
                if ($before[$field] !== $value) {
                    $changed[$field] = $value;
                }
            }

            if ($changed === []) {
                throw ValidationException::withMessages([
                    'review_reason' => 'No reviewed person values changed.',
                ]);
            }

            $toRevision = $locked->metadata_revision + 1;
            $locked->forceFill([
                ...$changed,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => $toRevision,
            ])->save();

            ArchivePersonRevision::createImmutable([
                'archive_person_id' => $locked->id,
                'revision_number' => $toRevision,
                'actor_user_id' => $actor->id,
                'from_revision' => $expectedRevision,
                'to_revision' => $toRevision,
                'changed_fields' => array_keys($changed),
                'before_values' => $before,
                'after_values' => $this->snapshot($locked),
                'change_reason' => $values['review_reason'],
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $values */
    private function assertValid(array $values): void
    {
        $this->assertDate(
            'birth',
            PersonDatePrecision::from($values['birth_precision']),
            $values['birth_on'],
            $values['birth_year'],
            $values['birth_decade']
        );
        $this->assertDate(
            'death',
            PersonDatePrecision::from($values['death_precision']),
            $values['death_on'],
            $values['death_year'],
            $values['death_decade']
        );

        if ($values['life_state'] === 'living'
            && PersonDatePrecision::from($values['death_precision']) !== PersonDatePrecision::Unknown) {
            throw ValidationException::withMessages([
                'death_precision' => 'A living person cannot have a reviewed death date.',
            ]);
        }

        $birthLower = $this->lowerYear(
            $values['birth_on'],
            $values['birth_year'],
            $values['birth_decade']
        );
        $deathLower = $this->lowerYear(
            $values['death_on'],
            $values['death_year'],
            $values['death_decade']
        );

        if ($birthLower !== null && $deathLower !== null && $deathLower < $birthLower) {
            throw ValidationException::withMessages([
                'death_precision' => 'The death evidence cannot precede the birth evidence.',
            ]);
        }

        if ($values['family_branch_id'] !== null) {
            $branch = FamilyBranch::query()->find((int) $values['family_branch_id']);

            if ($branch === null || $branch->review_state !== KnowledgeReviewState::Accepted) {
                throw ValidationException::withMessages([
                    'family_branch_id' => 'People may reference only accepted reviewed branches.',
                ]);
            }
        }

        $hasReviewedFacts = PersonNameCertainty::from($values['name_certainty']) !== PersonNameCertainty::Unknown
            || PersonDatePrecision::from($values['birth_precision']) !== PersonDatePrecision::Unknown
            || PersonDatePrecision::from($values['death_precision']) !== PersonDatePrecision::Unknown;

        if ($hasReviewedFacts && blank($values['source_note'])) {
            throw ValidationException::withMessages([
                'source_note' => 'Reviewed person facts require a source note.',
            ]);
        }
    }

    private function assertDate(
        string $field,
        PersonDatePrecision $precision,
        ?string $date,
        ?int $year,
        ?int $decade
    ): void {
        $invalid = match ($precision) {
            PersonDatePrecision::Exact, PersonDatePrecision::Approximate => $date === null || $year !== null || $decade !== null,
            PersonDatePrecision::YearOnly => $date !== null || $year === null || $decade !== null,
            PersonDatePrecision::DecadeOnly => $date !== null || $year !== null || $decade === null,
            PersonDatePrecision::Unknown => $date !== null || $year !== null || $decade !== null,
        };

        if ($invalid || ($decade !== null && $decade % 10 !== 0)) {
            throw ValidationException::withMessages([
                "{$field}_precision" => "The {$field} facts conflict with the selected precision.",
            ]);
        }
    }

    private function lowerYear(?string $date, ?int $year, ?int $decade): ?int
    {
        if ($date !== null) {
            return (int) substr($date, 0, 4);
        }

        return $year ?? $decade;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        $alternateNames = [];
        $rawAlternateNames = $input['alternate_names'] ?? [];

        if (is_array($rawAlternateNames)) {
            foreach ($rawAlternateNames as $name) {
                if (is_string($name) && trim($name) !== '') {
                    $alternateNames[] = trim($name);
                }
            }
        }

        $alternateNames = array_values(array_unique($alternateNames));

        return [
            'display_name' => trim((string) $input['display_name']),
            'alternate_names' => $alternateNames === [] ? null : $alternateNames,
            'name_certainty' => PersonNameCertainty::from((string) $input['name_certainty'])->value,
            'birth_on' => filled($input['birth_on']) ? (string) $input['birth_on'] : null,
            'birth_year' => filled($input['birth_year']) ? (int) $input['birth_year'] : null,
            'birth_decade' => filled($input['birth_decade']) ? (int) $input['birth_decade'] : null,
            'birth_precision' => PersonDatePrecision::from((string) $input['birth_precision'])->value,
            'death_on' => filled($input['death_on']) ? (string) $input['death_on'] : null,
            'death_year' => filled($input['death_year']) ? (int) $input['death_year'] : null,
            'death_decade' => filled($input['death_decade']) ? (int) $input['death_decade'] : null,
            'death_precision' => PersonDatePrecision::from((string) $input['death_precision'])->value,
            'life_state' => (string) $input['life_state'],
            'fact_confidence' => StructuredDateConfidence::from((string) $input['fact_confidence'])->value,
            'source_note' => filled($input['source_note']) ? trim((string) $input['source_note']) : null,
            'is_private' => (bool) $input['is_private'],
            'family_branch_id' => filled($input['family_branch_id']) ? (int) $input['family_branch_id'] : null,
            'notes' => filled($input['notes']) ? trim((string) $input['notes']) : null,
            'review_state' => KnowledgeReviewState::from((string) $input['review_state'])->value,
            'review_reason' => trim((string) $input['review_reason']),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(ArchivePerson $person): array
    {
        return [
            'display_name' => $person->display_name,
            'alternate_names' => $person->alternate_names,
            'name_certainty' => $person->name_certainty->value,
            'birth_on' => $person->birth_on?->format('Y-m-d'),
            'birth_year' => $person->birth_year,
            'birth_decade' => $person->birth_decade,
            'birth_precision' => $person->birth_precision->value,
            'death_on' => $person->death_on?->format('Y-m-d'),
            'death_year' => $person->death_year,
            'death_decade' => $person->death_decade,
            'death_precision' => $person->death_precision->value,
            'life_state' => $person->life_state,
            'fact_confidence' => $person->fact_confidence->value,
            'source_note' => $person->source_note,
            'is_private' => $person->is_private,
            'family_branch_id' => $person->family_branch_id,
            'notes' => $person->notes,
            'review_state' => $person->review_state->value,
            'review_reason' => $person->review_reason ?? '',
        ];
    }
}
