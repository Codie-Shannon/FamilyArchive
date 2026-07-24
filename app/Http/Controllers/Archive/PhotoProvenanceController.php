<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Media\Models\MediaItem;
use App\Domain\Provenance\Actions\UpdatePhotoProvenance;
use App\Domain\Provenance\Models\MediaProvenance;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\DestroyPhotoProvenanceRequest;
use App\Http\Requests\Archive\StorePhotoProvenanceRequest;
use Illuminate\Http\RedirectResponse;

final class PhotoProvenanceController extends Controller
{
    public function store(
        StorePhotoProvenanceRequest $request,
        MediaItem $mediaItem,
        UpdatePhotoProvenance $action
    ): RedirectResponse {
        $source = SourceCollection::query()->findOrFail(
            $request->integer('source_collection_id')
        );
        $batchId = $request->integer('scan_batch_id');
        $batch = $batchId > 0 ? ScanBatch::query()->findOrFail($batchId) : null;

        $action->attach(
            $mediaItem,
            $source,
            $batch,
            $request->filled('note') ? trim((string) $request->input('note')) : null,
            trim((string) $request->input('change_reason')),
            $request->integer('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.photos.show', $mediaItem)
            ->with('status', 'Source provenance attached and revision recorded.');
    }

    public function destroy(
        DestroyPhotoProvenanceRequest $request,
        MediaItem $mediaItem,
        MediaProvenance $provenance,
        UpdatePhotoProvenance $action
    ): RedirectResponse {
        $action->detach(
            $mediaItem,
            $provenance,
            trim((string) $request->input('change_reason')),
            $request->integer('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.photos.show', $mediaItem)
            ->with('status', 'Source provenance removed and revision recorded.');
    }
}
