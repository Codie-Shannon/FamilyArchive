<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Models\ArchivePhotoEditDraft;
use App\Domain\Archive\Services\ArchivePhotoEditor;
use App\Domain\Archive\Services\ArchivePhotoSplitFamily;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Archive\Services\PhotoVisibilityManager;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class ArchivePhotoEditorController extends Controller
{
    public function index(
        Request $request,
        ArchiveSelectionManager $selections,
        ArchivePhotoEditor $editor,
        ArchivePhotoSplitFamily $splitFamilies,
        PhotoVisibilityManager $visibility,
    ): View {
        $singlePhotoId = $request->integer('single_photo');
        $ids = $singlePhotoId > 0
            ? collect([$singlePhotoId])
            : $selections->ids($request->user(), 'photos:visible');
        abort_if($ids->isEmpty(), 422, 'Select at least one photo to edit.');
        if ($singlePhotoId > 0) {
            $singlePhoto = MediaItem::query()->findOrFail($singlePhotoId);
            abort_unless($visibility->canManage($request->user(), $singlePhoto), 403);
        }

        $ordered = $splitFamilies->batchSources($ids, $request->user());
        abort_if($ordered->isEmpty(), 404);
        $requestedId = (int) $request->query('photo', $ids->first());
        $requested = MediaItem::query()->find($requestedId);
        $requestedSource = $requested instanceof MediaItem ? ($splitFamilies->sourceFor($requested) ?? $requested) : null;
        $batchCurrent = $requestedSource instanceof MediaItem
            ? ($ordered->firstWhere('id', $requestedSource->id) ?? $ordered->first())
            : $ordered->first();

        $selectedSplitId = $request->integer('split_photo');
        if ($selectedSplitId < 1 && $singlePhotoId > 0 && $singlePhotoId !== $batchCurrent->id) {
            $selectedSplitId = $singlePhotoId;
        }
        $splitFamily = $splitFamilies->forEditor($batchCurrent, $request->user(), $selectedSplitId ?: null);
        $selectedSplitIds = collect($splitFamily)->pluck('id')->map(static fn ($id): int => (int) $id);
        $current = $selectedSplitIds->contains($selectedSplitId)
            ? MediaItem::query()->find($selectedSplitId)
            : null;
        if (! $current instanceof MediaItem && $splitFamily === []
            && $batchCurrent->review_status === MediaReviewStatus::Approved
            && $batchCurrent->approved_at !== null) {
            $current = $batchCurrent;
        }

        $editableIds = $splitFamilies->editableIds($ordered, $request->user());
        $drafts = ArchivePhotoEditDraft::query()->where('user_id', $request->user()->id)
            ->whereIn('media_item_id', $editableIds)->get()->keyBy('media_item_id');

        return view('archive.photo-editor', [
            'photos' => $ordered, 'batchCurrent' => $batchCurrent, 'current' => $current,
            'draft' => $current instanceof MediaItem ? $drafts->get($current->id) : null,
            'draftCount' => $drafts->count(), 'isSplit' => $current instanceof MediaItem && $editor->isSplit($current),
            'previewOnly' => ! $current instanceof MediaItem,
            'singlePhotoMode' => $singlePhotoId > 0,
            'splitFamily' => $splitFamily,
            'returnTo' => (string) $request->query('return_to', route('archive.index', absolute: false)),
        ]);
    }

    public function draft(Request $request, MediaItem $mediaItem, ArchivePhotoEditor $editor): JsonResponse|RedirectResponse
    {
        $draft = $editor->saveDraft($mediaItem, $request->user(), $this->settings($request), $request->boolean('from_source_scan'));
        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'draft_id' => $draft->id]);
        }

        return back()->with('status', 'Draft saved. The archive photo has not changed yet.');
    }

    public function publish(Request $request, MediaItem $mediaItem, ArchivePhotoEditor $editor): RedirectResponse
    {
        $draft = ArchivePhotoEditDraft::query()->where('user_id', $request->user()->id)
            ->where('media_item_id', $mediaItem->id)->firstOrFail();
        try {
            $editor->publish($draft, $request->user());
        } catch (DerivativeGenerationException $exception) {
            report($exception);

            return back()->withErrors(['editor' => $exception->getMessage()]);
        }

        return back()->with('status', 'Photo edit saved. The immutable original remains preserved.');
    }

    public function publishAll(
        Request $request,
        ArchivePhotoEditor $editor,
        ArchiveSelectionManager $selections,
        ArchivePhotoSplitFamily $splitFamilies,
    ): RedirectResponse {
        $selectedIds = $selections->ids($request->user(), 'photos:visible');
        $sources = $splitFamilies->batchSources($selectedIds, $request->user());
        $editableIds = $splitFamilies->editableIds($sources, $request->user());
        $drafts = ArchivePhotoEditDraft::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('media_item_id', $editableIds)
            ->orderBy('id')
            ->get();
        $saved = 0;
        $skipped = 0;
        foreach ($drafts as $draft) {
            try {
                $editor->publish($draft, $request->user());
                $saved++;
            } catch (Throwable) {
                $skipped++;
            }
        }

        return back()->with('status', "$saved changed photos saved; $skipped skipped for review.");
    }

    /** @return array<string, bool|float|int> */
    private function settings(Request $request): array
    {
        $validated = $request->validate([
            'orient' => ['nullable', 'boolean'], 'quarter_turn' => ['required', 'integer', 'between:-2,2'], 'straighten' => ['required', 'numeric', 'between:-8,8'],
            'crop_left' => ['required', 'numeric', 'between:0,80'], 'crop_top' => ['required', 'numeric', 'between:0,80'], 'crop_right' => ['required', 'numeric', 'between:0,80'], 'crop_bottom' => ['required', 'numeric', 'between:0,80'],
            'brightness' => ['required', 'integer', 'between:-40,40'], 'contrast' => ['required', 'integer', 'between:-30,30'], 'red' => ['required', 'integer', 'between:-20,20'], 'green' => ['required', 'integer', 'between:-20,20'], 'blue' => ['required', 'integer', 'between:-20,20'],
            'denoise' => ['required', 'integer', 'between:0,3'], 'sharpen' => ['required', 'integer', 'between:0,2'], 'cleanup' => ['required', 'integer', 'between:0,3'], 'from_source_scan' => ['nullable', 'boolean'],
        ]);
        $validated['orient'] = $request->boolean('orient');
        unset($validated['from_source_scan']);

        return $validated;
    }
}
