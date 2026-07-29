<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveEventRevision;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LinkReviewedMediaToEvent
{
    public function handle(
        ArchiveEvent $event,
        MediaItem $mediaItem,
        string $confidence,
        string $sourceNote,
        string $reason,
        int $expectedRevision,
        User $actor
    ): void {
        DB::transaction(function () use ($event, $mediaItem, $confidence, $sourceNote, $reason, $expectedRevision, $actor): void {
            $locked = ArchiveEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The event changed after this form was opened.',
                ]);
            }

            $approvedMedia = MediaItem::query()
                ->whereKey($mediaItem->id)
                ->where('review_status', MediaReviewStatus::Approved)
                ->whereNotNull('approved_at')
                ->first();

            if ($approvedMedia === null) {
                throw ValidationException::withMessages([
                    'media_item_id' => 'Only approved archive media may be linked to an event.',
                ]);
            }

            if ($locked->mediaItems()->whereKey($approvedMedia->id)->exists()) {
                throw ValidationException::withMessages([
                    'media_item_id' => 'This media item is already linked to the event.',
                ]);
            }

            $before = $this->snapshot($locked);
            $locked->mediaItems()->attach($approvedMedia->id, [
                'confidence' => $confidence,
                'source_note' => trim($sourceNote),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $toRevision = $locked->metadata_revision + 1;
            $locked->forceFill([
                'metadata_revision' => $toRevision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            ArchiveEventRevision::createImmutable([
                'archive_event_id' => $locked->id,
                'revision_number' => $toRevision,
                'actor_user_id' => $actor->id,
                'from_revision' => $expectedRevision,
                'to_revision' => $toRevision,
                'changed_fields' => ['media_links'],
                'before_values' => ['media_links' => $before],
                'after_values' => ['media_links' => $this->snapshot($locked)],
                'change_reason' => $reason,
                'created_at' => now(),
            ]);
        });
    }

    /** @return list<array{archive_id: string, confidence: string, source_note: string}> */
    private function snapshot(ArchiveEvent $event): array
    {
        $result = [];
        $media = $event->mediaItems()
            ->select('media_items.id', 'media_items.archive_id')
            ->orderBy('media_items.archive_id')
            ->get();

        foreach ($media as $item) {
            $result[] = [
                'archive_id' => $item->archive_id,
                'confidence' => (string) $item->pivot->confidence,
                'source_note' => (string) $item->pivot->source_note,
            ];
        }

        return $result;
    }
}
