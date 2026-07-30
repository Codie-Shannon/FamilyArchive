<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\AttachFamilyBranchProvenance;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreKnowledgeProvenanceRequest;
use Illuminate\Http\RedirectResponse;

final class FamilyBranchProvenanceController extends Controller
{
    public function store(
        StoreKnowledgeProvenanceRequest $request,
        FamilyBranch $familyBranch,
        AttachFamilyBranchProvenance $action
    ): RedirectResponse {
        $source = SourceCollection::query()->findOrFail(
            (int) $request->validated('source_collection_id')
        );
        $batchId = $request->validated('scan_batch_id');
        $batch = $batchId === null
            ? null
            : ScanBatch::query()->findOrFail((int) $batchId);

        $action->handle(
            $familyBranch,
            $source,
            $batch,
            $request->validated('note'),
            (string) $request->validated('change_reason'),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.branches.show', $familyBranch)
            ->with('status', 'Reviewed family branch provenance attached.');
    }
}
