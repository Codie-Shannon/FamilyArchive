<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\AttachEventProvenance;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreEventProvenanceRequest;
use Illuminate\Http\RedirectResponse;

final class EventProvenanceController extends Controller
{
    public function store(
        StoreEventProvenanceRequest $request,
        ArchiveEvent $archiveEvent,
        AttachEventProvenance $action
    ): RedirectResponse {
        $source = SourceCollection::query()->findOrFail(
            (int) $request->validated('source_collection_id')
        );
        $batchId = $request->validated('scan_batch_id');
        $batch = $batchId === null
            ? null
            : ScanBatch::query()->findOrFail((int) $batchId);

        $action->handle(
            $archiveEvent,
            $source,
            $batch,
            $request->validated('note'),
            (string) $request->validated('change_reason'),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.events.show', $archiveEvent)
            ->with('status', 'Reviewed event provenance attached.');
    }
}
