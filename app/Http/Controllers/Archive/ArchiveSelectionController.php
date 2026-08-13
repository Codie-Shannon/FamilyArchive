<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ArchiveSelectionController extends Controller
{
    public function update(Request $request, MediaItem $mediaItem, ArchiveSelectionManager $selections, ArchiveAccess $access): JsonResponse
    {
        $validated = $request->validate([
            'context' => ['required', 'string', 'max:120'],
            'selected' => ['required', 'boolean'],
            'source_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $context = $selections->normalizeContext($validated['context']);
        abort_unless($mediaItem->media_type === MediaType::Photo
            && $mediaItem->review_status === MediaReviewStatus::Approved, 404);

        if (str_starts_with($context, 'album:')) {
            abort_unless($request->user()->canManageTrustedIntake() && $access->canView($request->user(), $mediaItem), 403);
        } elseif ($context === 'photos:hidden') {
            abort_unless($mediaItem->hidden_at !== null
                && ($request->user()->role === 'owner' || $mediaItem->created_by === $request->user()->id), 403);
        } else {
            abort_unless($mediaItem->hidden_at === null
                && $access->canView($request->user(), $mediaItem)
                && ($request->user()->role === 'owner' || $mediaItem->created_by === $request->user()->id), 403);
        }

        $summary = $selections->set($request->user(), $context, $mediaItem, (bool) $validated['selected'], (int) ($validated['source_page'] ?? 1));

        return response()->json([
            'selected_count' => $summary['count'],
            'selected_page_count' => $summary['page_count'],
            'selected_ids' => $summary['selected_ids'],
        ]);
    }

    public function clear(Request $request, ArchiveSelectionManager $selections): JsonResponse
    {
        $validated = $request->validate(['context' => ['required', 'string', 'max:120']]);
        $selections->clear($request->user(), $validated['context']);

        return response()->json(['selected_count' => 0, 'selected_page_count' => 0, 'selected_ids' => []]);
    }
}
