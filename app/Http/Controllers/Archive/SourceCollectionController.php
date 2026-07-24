<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreSourceCollectionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class SourceCollectionController extends Controller
{
    public function index(): View
    {
        return view('archive.sources.index', [
            'sources' => SourceCollection::query()
                ->withCount(['scanBatches', 'mediaItems'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('archive.sources.create');
    }

    public function store(StoreSourceCollectionRequest $request): RedirectResponse
    {
        $source = SourceCollection::query()->create([
            ...$request->validated(),
            'source_id' => 'SRC-'.Str::upper((string) Str::ulid()),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('archive.sources.show', $source)
            ->with('status', "Source {$source->source_id} created.");
    }

    public function show(SourceCollection $sourceCollection): View
    {
        $sourceCollection->load([
            'scanBatches' => fn ($query) => $query->orderByDesc('scanned_on')->orderBy('label'),
            'provenanceLinks' => fn ($query) => $query
                ->with(['mediaItem', 'scanBatch'])
                ->whereHas('mediaItem', fn ($media) => $media
                    ->where('review_status', MediaReviewStatus::Approved)
                    ->whereNotNull('approved_at'))
                ->orderByDesc('id'),
        ]);

        return view('archive.sources.show', [
            'source' => $sourceCollection,
        ]);
    }
}
