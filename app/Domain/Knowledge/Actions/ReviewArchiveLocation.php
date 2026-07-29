<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchiveLocationRevision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewArchiveLocation
{
    /**
     * @param array{
     *   label: string,
     *   country_code: ?string,
     *   region: ?string,
     *   locality: ?string,
     *   precision: string,
     *   is_sensitive: bool,
     *   review_state: string,
     *   confidence: string,
     *   source_note: ?string,
     *   review_reason: string
     * } $input
     */
    public function create(array $input, User $actor): ArchiveLocation
    {
        $values = $this->normalize($input);
        $this->assertValid($values);

        return DB::transaction(function () use ($values, $actor): ArchiveLocation {
            $location = ArchiveLocation::query()->create([
                ...$values,
                'location_id' => 'LOC-'.Str::upper((string) Str::ulid()),
                'created_by' => $actor->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ]);

            ArchiveLocationRevision::createImmutable([
                'archive_location_id' => $location->id,
                'revision_number' => 1,
                'actor_user_id' => $actor->id,
                'from_revision' => 0,
                'to_revision' => 1,
                'changed_fields' => array_keys($values),
                'before_values' => [],
                'after_values' => $this->snapshot($location),
                'change_reason' => $values['review_reason'],
                'created_at' => now(),
            ]);

            return $location->fresh();
        });
    }

    /**
     * @param array{
     *   label: string,
     *   country_code: ?string,
     *   region: ?string,
     *   locality: ?string,
     *   precision: string,
     *   is_sensitive: bool,
     *   review_state: string,
     *   confidence: string,
     *   source_note: ?string,
     *   review_reason: string
     * } $input
     */
    public function update(
        ArchiveLocation $location,
        array $input,
        int $expectedRevision,
        User $actor
    ): ArchiveLocation {
        $values = $this->normalize($input);
        $this->assertValid($values);

        return DB::transaction(function () use ($location, $values, $expectedRevision, $actor): ArchiveLocation {
            $locked = ArchiveLocation::query()->lockForUpdate()->findOrFail($location->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The location changed after this form was opened.',
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
                    'review_reason' => 'No reviewed location values changed.',
                ]);
            }

            $toRevision = $locked->metadata_revision + 1;
            $locked->forceFill([
                ...$changed,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'metadata_revision' => $toRevision,
            ])->save();

            ArchiveLocationRevision::createImmutable([
                'archive_location_id' => $locked->id,
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
        if ($values['is_sensitive'] !== ($values['precision'] === LocationPrecision::Private->value)) {
            throw ValidationException::withMessages([
                'precision' => 'Sensitive locations must use private precision, and private precision must be sensitive.',
            ]);
        }

        if ($values['review_state'] === KnowledgeReviewState::Accepted->value
            && ($values['source_note'] === null || $values['source_note'] === '')) {
            throw ValidationException::withMessages([
                'source_note' => 'Accepted locations require a reviewed source note.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   label: string,
     *   country_code: ?string,
     *   region: ?string,
     *   locality: ?string,
     *   precision: string,
     *   is_sensitive: bool,
     *   review_state: string,
     *   confidence: string,
     *   source_note: ?string,
     *   review_reason: string
     * }
     */
    private function normalize(array $input): array
    {
        return [
            'label' => trim((string) $input['label']),
            'country_code' => filled($input['country_code'])
                ? Str::upper(trim((string) $input['country_code']))
                : null,
            'region' => filled($input['region']) ? trim((string) $input['region']) : null,
            'locality' => filled($input['locality']) ? trim((string) $input['locality']) : null,
            'precision' => LocationPrecision::from((string) $input['precision'])->value,
            'is_sensitive' => (bool) $input['is_sensitive'],
            'review_state' => KnowledgeReviewState::from((string) $input['review_state'])->value,
            'confidence' => StructuredDateConfidence::from((string) $input['confidence'])->value,
            'source_note' => filled($input['source_note']) ? trim((string) $input['source_note']) : null,
            'review_reason' => trim((string) $input['review_reason']),
        ];
    }

    /**
     * @return array{
     *   label: string,
     *   country_code: ?string,
     *   region: ?string,
     *   locality: ?string,
     *   precision: string,
     *   is_sensitive: bool,
     *   review_state: string,
     *   confidence: string,
     *   source_note: ?string,
     *   review_reason: string
     * }
     */
    private function snapshot(ArchiveLocation $location): array
    {
        return [
            'label' => $location->label,
            'country_code' => $location->country_code,
            'region' => $location->region,
            'locality' => $location->locality,
            'precision' => $location->precision->value,
            'is_sensitive' => $location->is_sensitive,
            'review_state' => $location->review_state->value,
            'confidence' => $location->confidence->value,
            'source_note' => $location->source_note,
            'review_reason' => $location->review_reason ?? '',
        ];
    }
}
