<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveEventRevision;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewArchiveEvent
{
    /** @param array<string, mixed> $input */
    public function create(array $input, User $actor): ArchiveEvent
    {
        $values = $this->normalize($input);
        $this->assertValid($values);

        return DB::transaction(function () use ($values, $actor): ArchiveEvent {
            $event = ArchiveEvent::query()->create([
                ...$values,
                'event_id' => 'EVT-'.Str::upper((string) Str::ulid()),
                'created_by' => $actor->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ]);

            ArchiveEventRevision::createImmutable([
                'archive_event_id' => $event->id,
                'revision_number' => 1,
                'actor_user_id' => $actor->id,
                'from_revision' => 0,
                'to_revision' => 1,
                'changed_fields' => array_keys($values),
                'before_values' => [],
                'after_values' => $this->snapshot($event),
                'change_reason' => $values['review_reason'],
                'created_at' => now(),
            ]);

            return $event->fresh();
        });
    }

    /** @param array<string, mixed> $input */
    public function update(
        ArchiveEvent $event,
        array $input,
        int $expectedRevision,
        User $actor
    ): ArchiveEvent {
        $values = $this->normalize($input);
        $this->assertValid($values);

        return DB::transaction(function () use ($event, $values, $expectedRevision, $actor): ArchiveEvent {
            $locked = ArchiveEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The event changed after this form was opened.',
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
                    'review_reason' => 'No reviewed event values changed.',
                ]);
            }

            $toRevision = $locked->metadata_revision + 1;
            $locked->forceFill([
                ...$changed,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => $toRevision,
            ])->save();

            ArchiveEventRevision::createImmutable([
                'archive_event_id' => $locked->id,
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
        $precision = DatePrecision::from($values['date_precision']);
        $hasStart = $values['starts_on'] !== null;
        $hasEnd = $values['ends_on'] !== null;
        $hasYear = $values['date_year'] !== null;
        $hasDecade = $values['estimated_decade'] !== null;

        $invalid = match ($precision) {
            DatePrecision::Exact, DatePrecision::Approximate => ! $hasStart || $hasYear || $hasDecade,
            DatePrecision::YearOnly => $hasStart || $hasEnd || ! $hasYear || $hasDecade,
            DatePrecision::DecadeOnly => $hasStart || $hasEnd || $hasYear || ! $hasDecade,
            DatePrecision::Unknown => $hasStart || $hasEnd || $hasYear || $hasDecade,
        };

        if ($invalid) {
            throw ValidationException::withMessages([
                'date_precision' => 'The supplied date facts conflict with the selected precision.',
            ]);
        }

        if ($hasEnd && $values['ends_on'] < $values['starts_on']) {
            throw ValidationException::withMessages([
                'ends_on' => 'The event end date must not precede its start date.',
            ]);
        }

        if ($hasDecade && $values['estimated_decade'] % 10 !== 0) {
            throw ValidationException::withMessages([
                'estimated_decade' => 'A decade must be a year ending in zero.',
            ]);
        }

        if ($precision === DatePrecision::Unknown
            && $values['date_confidence'] !== StructuredDateConfidence::Unknown->value) {
            throw ValidationException::withMessages([
                'date_confidence' => 'An unknown date must have unknown confidence.',
            ]);
        }

        if ($precision !== DatePrecision::Unknown
            && ($values['date_source_note'] === null || $values['date_source_note'] === '')) {
            throw ValidationException::withMessages([
                'date_source_note' => 'Reviewed event dates require a source note.',
            ]);
        }

        if ($values['archive_location_id'] !== null) {
            $location = ArchiveLocation::query()->find((int) $values['archive_location_id']);
            if ($location === null || $location->review_state !== KnowledgeReviewState::Accepted) {
                throw ValidationException::withMessages([
                    'archive_location_id' => 'Events may reference only accepted reviewed locations.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        return [
            'name' => trim((string) $input['name']),
            'type' => EventType::from((string) $input['type'])->value,
            'starts_on' => filled($input['starts_on']) ? (string) $input['starts_on'] : null,
            'ends_on' => filled($input['ends_on']) ? (string) $input['ends_on'] : null,
            'date_precision' => DatePrecision::from((string) $input['date_precision'])->value,
            'date_year' => filled($input['date_year']) ? (int) $input['date_year'] : null,
            'estimated_decade' => filled($input['estimated_decade']) ? (int) $input['estimated_decade'] : null,
            'date_confidence' => StructuredDateConfidence::from((string) $input['date_confidence'])->value,
            'date_source_note' => filled($input['date_source_note']) ? trim((string) $input['date_source_note']) : null,
            'archive_location_id' => filled($input['archive_location_id']) ? (int) $input['archive_location_id'] : null,
            'description' => filled($input['description']) ? trim((string) $input['description']) : null,
            'review_state' => KnowledgeReviewState::from((string) $input['review_state'])->value,
            'review_reason' => trim((string) $input['review_reason']),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(ArchiveEvent $event): array
    {
        return [
            'name' => $event->name,
            'type' => $event->type->value,
            'starts_on' => $event->starts_on?->format('Y-m-d'),
            'ends_on' => $event->ends_on?->format('Y-m-d'),
            'date_precision' => $event->date_precision->value,
            'date_year' => $event->date_year,
            'estimated_decade' => $event->estimated_decade,
            'date_confidence' => $event->date_confidence->value,
            'date_source_note' => $event->date_source_note,
            'archive_location_id' => $event->archive_location_id,
            'description' => $event->description,
            'review_state' => $event->review_state->value,
            'review_reason' => $event->review_reason ?? '',
        ];
    }
}
