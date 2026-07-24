<?php

namespace App\Domain\Provenance\Actions;

use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Domain\Provenance\Models\MediaProvenance;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePhotoProvenance
{
    public function attach(
        MediaItem $mediaItem,
        SourceCollection $source,
        ?ScanBatch $batch,
        ?string $note,
        string $reason,
        int $expectedRevision,
        User $actor
    ): MediaProvenance {
        return DB::transaction(function () use ($mediaItem, $source, $batch, $note, $reason, $expectedRevision, $actor): MediaProvenance {
            $locked = $this->lockEligiblePhoto($mediaItem, $expectedRevision);
            $this->assertBatchBelongsToSource($batch, $source);

            $duplicate = MediaProvenance::query()
                ->where('media_item_id', $locked->id)
                ->where('source_collection_id', $source->id)
                ->where('scan_batch_id', $batch?->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'source_collection_id' => 'This provenance link already exists.',
                ]);
            }

            $before = $this->snapshot($locked);
            $link = MediaProvenance::query()->create([
                'media_item_id' => $locked->id,
                'source_collection_id' => $source->id,
                'scan_batch_id' => $batch?->id,
                'note' => $note,
                'attached_by' => $actor->id,
            ]);
            $after = $this->snapshot($locked);

            $this->recordRevision($locked, $actor, $reason, $before, $after);

            return $link->load(['sourceCollection', 'scanBatch']);
        });
    }

    public function detach(
        MediaItem $mediaItem,
        MediaProvenance $link,
        string $reason,
        int $expectedRevision,
        User $actor
    ): void {
        DB::transaction(function () use ($mediaItem, $link, $reason, $expectedRevision, $actor): void {
            $locked = $this->lockEligiblePhoto($mediaItem, $expectedRevision);
            $lockedLink = MediaProvenance::query()->lockForUpdate()->findOrFail($link->id);

            abort_unless($lockedLink->media_item_id === $locked->id, 404);

            $before = $this->snapshot($locked);
            $lockedLink->delete();
            $after = $this->snapshot($locked);

            $this->recordRevision($locked, $actor, $reason, $before, $after);
        });
    }

    private function lockEligiblePhoto(MediaItem $mediaItem, int $expectedRevision): MediaItem
    {
        $locked = MediaItem::query()->lockForUpdate()->findOrFail($mediaItem->id);

        abort_unless(
            $locked->media_type === MediaType::Photo
            && $locked->review_status === MediaReviewStatus::Approved
            && $locked->approved_at !== null,
            404
        );

        if ($locked->metadata_revision !== $expectedRevision) {
            throw ValidationException::withMessages([
                'expected_metadata_revision' => 'The metadata changed after this form was opened.',
            ]);
        }

        return $locked;
    }

    private function assertBatchBelongsToSource(?ScanBatch $batch, SourceCollection $source): void
    {
        if ($batch !== null && $batch->source_collection_id !== $source->id) {
            throw ValidationException::withMessages([
                'scan_batch_id' => 'The scan batch does not belong to the selected source.',
            ]);
        }
    }

    /** @return list<array{source_id: string, scan_batch_id: ?string, note: ?string}> */
    private function snapshot(MediaItem $mediaItem): array
    {
        $result = [];
        $links = MediaProvenance::query()
            ->with(['sourceCollection:id,source_id', 'scanBatch:id,scan_batch_id'])
            ->where('media_item_id', $mediaItem->id)
            ->orderBy('id')
            ->get();

        foreach ($links as $link) {
            $result[] = [
                'source_id' => $link->sourceCollection->source_id,
                'scan_batch_id' => $link->scanBatch?->scan_batch_id,
                'note' => $link->note,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{source_id: string, scan_batch_id: ?string, note: ?string}>  $before
     * @param  list<array{source_id: string, scan_batch_id: ?string, note: ?string}>  $after
     */
    private function recordRevision(
        MediaItem $mediaItem,
        User $actor,
        string $reason,
        array $before,
        array $after
    ): void {
        $fromRevision = $mediaItem->metadata_revision;
        $toRevision = $fromRevision + 1;

        $mediaItem->forceFill(['metadata_revision' => $toRevision])->save();

        PhotoMetadataRevision::createImmutable([
            'media_item_id' => $mediaItem->id,
            'revision_number' => $toRevision,
            'actor_user_id' => $actor->id,
            'from_revision' => $fromRevision,
            'to_revision' => $toRevision,
            'changed_fields' => ['source_provenance'],
            'before_values' => ['source_provenance' => $before],
            'after_values' => ['source_provenance' => $after],
            'change_reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
