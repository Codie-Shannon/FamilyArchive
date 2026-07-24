<?php

namespace App\Domain\Metadata\Actions;

use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Exceptions\NoEffectiveMetadataChange;
use App\Domain\Metadata\Exceptions\StaleMetadataRevision;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdatePhotoMetadata
{
    /**
     * @param  array{
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
     * }  $input
     */
    public function handle(
        MediaItem $mediaItem,
        User $actor,
        array $input
    ): MediaItem {
        return DB::transaction(function () use ($mediaItem, $actor, $input): MediaItem {
            /** @var MediaItem $locked */
            $locked = MediaItem::query()
                ->whereKey($mediaItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $locked->media_type === MediaType::Photo
                && $locked->review_status === MediaReviewStatus::Approved
                && $locked->approved_at !== null,
                404
            );

            if ($locked->metadata_revision !== $input['expected_metadata_revision']) {
                throw new StaleMetadataRevision(
                    'The metadata changed after this form was opened.'
                );
            }

            $before = [];
            $after = [];

            foreach ([
                'title',
                'description',
                'story',
                'date_precision',
                'canonical_date',
                'date_year',
                'estimated_decade',
                'structured_date_confidence',
                'date_review_state',
                'date_source_note',
                'date_reason',
            ] as $field) {
                $current = $locked->getAttribute($field);
                $next = $input[$field];

                if ($field === 'canonical_date') {
                    $current = $locked->canonical_date?->format('Y-m-d');
                } elseif ($current instanceof \BackedEnum) {
                    $current = $current->value;
                }

                if ($current !== $next) {
                    $before[$field] = $current;
                    $after[$field] = $next;
                }
            }

            if ($after === []) {
                throw new NoEffectiveMetadataChange(
                    'No metadata values changed.'
                );
            }

            $fromRevision = $locked->metadata_revision;
            $toRevision = $fromRevision + 1;

            $locked->forceFill([
                ...$after,
                'metadata_revision' => $toRevision,
            ])->save();

            PhotoMetadataRevision::createImmutable([
                'media_item_id' => $locked->id,
                'revision_number' => $toRevision,
                'actor_user_id' => $actor->id,
                'from_revision' => $fromRevision,
                'to_revision' => $toRevision,
                'changed_fields' => array_keys($after),
                'before_values' => $before,
                'after_values' => $after,
                'change_reason' => $input['change_reason'],
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
