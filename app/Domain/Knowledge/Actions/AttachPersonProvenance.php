<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\ArchivePersonProvenance;
use App\Domain\Knowledge\Models\ArchivePersonRevision;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachPersonProvenance
{
    public function handle(
        ArchivePerson $person,
        SourceCollection $source,
        ?ScanBatch $batch,
        ?string $note,
        string $reason,
        int $expectedRevision,
        User $actor
    ): ArchivePersonProvenance {
        return DB::transaction(function () use ($person, $source, $batch, $note, $reason, $expectedRevision, $actor): ArchivePersonProvenance {
            $locked = ArchivePerson::query()->lockForUpdate()->findOrFail($person->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The person record changed after this form was opened.',
                ]);
            }

            if ($batch !== null && $batch->source_collection_id !== $source->id) {
                throw ValidationException::withMessages([
                    'scan_batch_id' => 'The scan batch does not belong to the selected source.',
                ]);
            }

            $duplicate = ArchivePersonProvenance::query()
                ->where('archive_person_id', $locked->id)
                ->where('source_collection_id', $source->id)
                ->where('scan_batch_id', $batch?->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'source_collection_id' => 'This person provenance link already exists.',
                ]);
            }

            $before = $this->snapshot($locked);
            $link = ArchivePersonProvenance::query()->create([
                'archive_person_id' => $locked->id,
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

            ArchivePersonRevision::createImmutable([
                'archive_person_id' => $locked->id,
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
    private function snapshot(ArchivePerson $person): array
    {
        $result = [];
        $links = ArchivePersonProvenance::query()
            ->with(['sourceCollection:id,source_id', 'scanBatch:id,scan_batch_id'])
            ->where('archive_person_id', $person->id)
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
