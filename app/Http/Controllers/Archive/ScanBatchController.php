<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreScanBatchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class ScanBatchController extends Controller
{
    public function store(
        StoreScanBatchRequest $request,
        SourceCollection $sourceCollection
    ): RedirectResponse {
        $batch = ScanBatch::query()->create([
            ...$request->validated(),
            'scan_batch_id' => 'SCAN-'.Str::upper((string) Str::ulid()),
            'source_collection_id' => $sourceCollection->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('archive.sources.show', $sourceCollection)
            ->with('status', "Scan batch {$batch->scan_batch_id} created.");
    }
}
