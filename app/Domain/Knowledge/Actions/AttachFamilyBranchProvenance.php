<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\Models\FamilyBranchProvenance;
use App\Domain\Knowledge\Models\FamilyBranchRevision;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachFamilyBranchProvenance
{
    public function handle(
        FamilyBranch $branch,
        SourceCollection $source,
        ?ScanBatch $batch,
        ?string $note,
        string $reason,
        int $expectedRevision,
        User $actor
    ): FamilyBranchProvenance {
        return DB::transaction(function () use ($branch, $source, $batch, $note, $reason, $expectedRevision, $actor): FamilyBranchProvenance {
            $locked = FamilyBranch::query()->lockForUpdate()->findOrFail($branch->id);

            if ($locked->metadata_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_metadata_revision' => 'The family branch changed after this form was opened.',
                ]);
            }

            if ($batch !== null && $batch->source_collection_id !== $source->id) {
                throw ValidationException::withMessages([
                    'scan_batch_id' => 'The scan batch does not belong to the selected source.',
                ]);
            }

            $duplicate = FamilyBranchProvenance::query()
                ->where('family_branch_id', $locked->id)
                ->where('source_collection_id', $source->id)
                ->where('scan_batch_id', $batch?->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'source_collection_id' => 'This family branch provenance link already exists.',
                ]);
            }

            $before = $this->snapshot($locked);
            $link = FamilyBranchProvenance::query()->create([
                'family_branch_id' => $locked->id,
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

            FamilyBranchRevision::createImmutable([
                'family_branch_id' => $locked->id,
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
    private function snapshot(FamilyBranch $branch): array
    {
        $result = [];
        $links = FamilyBranchProvenance::query()
            ->with(['sourceCollection:id,source_id', 'scanBatch:id,scan_batch_id'])
            ->where('family_branch_id', $branch->id)
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
