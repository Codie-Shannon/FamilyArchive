<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Models\ArchivePhotoEditBatch;
use App\Domain\Archive\Models\ArchivePhotoEditDraft;
use App\Domain\Archive\Services\ArchivePhotoEditBatchPublisher;
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
        $requestedBatchId = (string) $request->session()->get('archive_photo_edit_batch_id', '');
        $batchQuery = ArchivePhotoEditBatch::query()->where('user_id', $request->user()->id);
        $batchEdit = filled($requestedBatchId)
            ? (clone $batchQuery)->where('batch_id', $requestedBatchId)->first()
            : (clone $batchQuery)->whereIn('state', ['queued', 'running'])->latest('id')->first();

        return view('archive.photo-editor', [
            'photos' => $ordered, 'batchCurrent' => $batchCurrent, 'current' => $current,
            'draft' => $current instanceof MediaItem ? $drafts->get($current->id) : null,
            'draftCount' => $drafts->count(), 'isSplit' => $current instanceof MediaItem && $editor->isSplit($current),
            'previewOnly' => ! $current instanceof MediaItem,
            'singlePhotoMode' => $singlePhotoId > 0,
            'splitFamily' => $splitFamily,
            'batchEdit' => $batchEdit,
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

    public function publish(
        Request $request,
        MediaItem $mediaItem,
        ArchivePhotoEditor $editor,
        ArchivePhotoEditBatchPublisher $batches,
    ): JsonResponse|RedirectResponse {
        if ($batches->hasActiveItem($request->user(), $mediaItem)) {
            $message = 'This photo is already being saved by the active batch.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : back()->withErrors(['editor' => $message]);
        }
        $draft = ArchivePhotoEditDraft::query()->where('user_id', $request->user()->id)
            ->where('media_item_id', $mediaItem->id)->firstOrFail();
        try {
            $editor->publish($draft, $request->user());
        } catch (DerivativeGenerationException $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['editor' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['published' => true]);
        }

        return back()->with('status', 'Photo edit saved. The immutable original remains preserved.');
    }

    public function publishAll(
        Request $request,
        ArchiveSelectionManager $selections,
        ArchivePhotoSplitFamily $splitFamilies,
        ArchivePhotoEditBatchPublisher $batches,
    ): RedirectResponse {
        $selectedIds = $selections->ids($request->user(), 'photos:visible');
        $sources = $splitFamilies->batchSources($selectedIds, $request->user());
        $editableIds = $splitFamilies->editableIds($sources, $request->user());
        $drafts = ArchivePhotoEditDraft::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('media_item_id', $editableIds)
            ->orderBy('id')
            ->get();
        $batch = $batches->start($request->user(), $drafts);

        return back()
            ->with('archive_photo_edit_batch_id', $batch->batch_id)
            ->with('status', $batch->total_count.' changed photos queued. You can leave this page while they save.');
    }

    public function batchStatus(Request $request, ArchivePhotoEditBatch $batch): JsonResponse
    {
        abort_unless($batch->user_id === $request->user()->id, 403);
        $batch->refresh();

        return response()->json([
            'state' => $batch->state,
            'total' => $batch->total_count,
            'completed' => $batch->completed_count,
            'failed' => $batch->failed_count,
            'processed' => $batch->completed_count + $batch->failed_count,
            'percent' => $batch->total_count > 0
                ? min(100, (int) round((($batch->completed_count + $batch->failed_count) / $batch->total_count) * 100))
                : 100,
            'active' => $batch->isActive(),
            'retryable' => $batch->state === 'completed_with_failures' && $batch->failed_count > 0,
        ]);
    }

    public function retryBatch(
        Request $request,
        ArchivePhotoEditBatch $batch,
        ArchivePhotoEditBatchPublisher $batches,
    ): RedirectResponse {
        $retried = $batches->retry($batch, $request->user());

        return back()
            ->with('archive_photo_edit_batch_id', $retried->batch_id)
            ->with('status', $retried->total_count.' photo-save checkpoints resumed.');
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
