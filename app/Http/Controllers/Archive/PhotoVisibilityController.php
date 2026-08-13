<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Archive\Services\PhotoVisibilityManager;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

final class PhotoVisibilityController extends Controller
{
    public function hideForm(Request $request, MediaItem $mediaItem, PhotoVisibilityManager $visibility): View
    {
        abort_unless($visibility->canManage($request->user(), $mediaItem) && $mediaItem->hidden_at === null, 403);

        return view('archive.hide-photo', compact('mediaItem'));
    }

    public function hideOne(Request $request, MediaItem $mediaItem, PhotoVisibilityManager $visibility, ArchiveSelectionManager $selections): RedirectResponse
    {
        $validated = $request->validate([
            'reason_category' => ['required', Rule::in(['family_request', 'privacy', 'incorrect_content', 'duplicate', 'orientation_quality', 'other'])],
            'reason_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('reason_category') === 'other')],
            'expected_metadata_revision' => ['required', 'integer', 'min:0'],
        ]);
        $visibility->hide($mediaItem, $request->user(), $validated['reason_category'], $validated['reason_note'] ?? null, false, (int) $validated['expected_metadata_revision']);
        $selections->clear($request->user(), 'photos:visible');

        return redirect()->route('archive.index')->with('status', 'Photo hidden from the family archive. The original and archive relationships remain preserved.');
    }

    public function hideBatch(Request $request, ArchiveSelectionManager $selections, PhotoVisibilityManager $visibility): RedirectResponse
    {
        $ids = $selections->ids($request->user(), 'photos:visible');
        abort_if($ids->count() < 2, 422, 'Select at least two photos for batch hiding.');
        $hidden = 0;
        $skipped = 0;
        foreach (MediaItem::query()->whereKey($ids)->get() as $item) {
            try {
                if (! $visibility->canManage($request->user(), $item)) {
                    $skipped++;

                    continue;
                }
                $hidden += $visibility->hide($item, $request->user(), 'batch_hidden', null, true) ? 1 : 0;
            } catch (Throwable) {
                $skipped++;
            }
        }
        $selections->clear($request->user(), 'photos:visible');

        return redirect()->route('archive.index')->with('status', "$hidden photos hidden; $skipped skipped. Originals and relationships remain preserved.");
    }

    public function restoreBatch(Request $request, ArchiveSelectionManager $selections, PhotoVisibilityManager $visibility): RedirectResponse
    {
        $ids = $selections->ids($request->user(), 'photos:hidden');
        abort_if($ids->isEmpty(), 422, 'Select at least one hidden photo to restore.');
        $restored = 0;
        $skipped = 0;
        foreach (MediaItem::query()->whereKey($ids)->get() as $item) {
            try {
                if (! $visibility->canManage($request->user(), $item)) {
                    $skipped++;

                    continue;
                }
                $restored += $visibility->restore($item, $request->user()) ? 1 : 0;
            } catch (Throwable) {
                $skipped++;
            }
        }
        $selections->clear($request->user(), 'photos:hidden');

        return redirect()->route('archive.photos.hidden')->with('status', "$restored photos restored to their previous visibility; $skipped skipped.");
    }
}
