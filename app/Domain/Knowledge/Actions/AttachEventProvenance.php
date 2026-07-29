<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveEventRevision;
use App\Domain\Knowledge\Models\EventProvenance;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachEventProvenance
{
    public function handle(
        ArchiveEvent $event,
        SourceCollection $source,
        ?ScanBatch $batch,
        ?string $note,
        string $reason,
        int $expectedRevision,
        User $actor
    ): EventProvenance {
        return DB::transaction(function () use ($event, $source, $batch, $note, $reason, $expectedRevision, $actor): EventProvenance {
            $locked = ArchiveEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The event changed after this form was opened.',
                ]);
            }

            if ($batch !== null && $batch->source_collection_id !== $source->id) {
                throw ValidationException::withMessages([
                    'scan_batch_id' => 'The scan batch does not belong to the selected source.',
                ]);
            }

            $duplicate = EventProvenance::query()
                ->where('archive_event_id', $locked->id)
                ->where('source_collection_id', $source->id)
                ->where('scan_batch_id', $batch?->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'source_collection_id' => 'This event provenance link already exists.',
                ]);
            }

            $before = $this->snapshot($locked);
            $link = EventProvenance::query()->create([
                'archive_event_id' => $locked->id,
                'source_collection_id' => $source->id,
                'scan_batch_id' => $batch?->id,
                'note' => filled($note) ? trim((string) $note) : null,
                'attached_by' => $actor->id,
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
                'changed_fields' => ['source_provenance'],
                'before_values' => ['source_provenance' => $before],
                'after_values' => ['source_provenance' => $this->snapshot($locked)],
                'change_reason' => $reason,
                'created_at' => now(),
            ]);

            return $link->load(['sourceCollection', 'scanBatch']);
        });
    }

    /** @return list<array{source_id: string, scan_batch_id: ?string, note: ?string}> */
    private function snapshot(ArchiveEvent $event): array
    {
        $result = [];
        $links = EventProvenance::query()
            ->with(['sourceCollection:id,source_id', 'scanBatch:id,scan_batch_id'])
            ->where('archive_event_id', $event->id)
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
}
