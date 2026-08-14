<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Services\ArchivePhotoEditingSource;
use App\Domain\Archive\Services\ArchivePhotoSplitter;
use App\Domain\Archive\Services\PhotoVisibilityManager;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ArchivePhotoSplitController extends Controller
{
    public function edit(
        Request $request,
        MediaItem $mediaItem,
        ArchivePhotoEditingSource $sources,
        PhotoVisibilityManager $visibility,
    ): View {
        $this->authorizePhoto($request, $mediaItem, $visibility);
        $current = $sources->current($mediaItem);
        $original = $sources->original($mediaItem);

        return view('archive.photo-split-editor', [
            'photo' => $mediaItem,
            'currentSource' => $current,
            'originalSource' => $original,
            'returnTo' => $this->returnTo($request, $mediaItem),
            'editorReturnTo' => $this->editorReturnTo($request, $mediaItem),
        ]);
    }

    public function publish(Request $request, MediaItem $mediaItem, ArchivePhotoSplitter $splitter): RedirectResponse
    {
        $validated = $request->validate([
            'expected_metadata_revision' => ['required', 'integer', 'min:0'],
            'source_basis' => ['required', 'in:current,original'],
            'regions_json' => ['required', 'string', 'max:50000'],
            'return_to' => ['nullable', 'string', 'max:2000'],
            'editor_return_to' => ['nullable', 'string', 'max:4000'],
        ]);
        try {
            $regions = json_decode($validated['regions_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['regions_json' => 'The split layout could not be read. Reload the editor and try again.']);
        }
        if (! is_array($regions)) {
            throw ValidationException::withMessages(['regions_json' => 'The split layout must contain photo regions.']);
        }
        $children = $splitter->split(
            $mediaItem,
            $request->user(),
            array_values($regions),
            (int) $validated['expected_metadata_revision'],
            $validated['source_basis'],
        );
        $first = $children[0];

        return redirect()->to($this->editorUrlWithSplit(
            (string) ($validated['editor_return_to'] ?? ''),
            $mediaItem,
            $first,
            (string) ($validated['return_to'] ?: route('archive.index', absolute: false)),
        ))->with('status', count($children).' split photos published. The source remains preserved and every split is grouped in edit mode.');
    }

    private function authorizePhoto(Request $request, MediaItem $item, PhotoVisibilityManager $visibility): void
    {
        abort_unless($visibility->canManage($request->user(), $item)
            && $item->media_type === MediaType::Photo
            && $item->review_status === MediaReviewStatus::Approved
            && $item->approved_at !== null, 403);
    }

    private function returnTo(Request $request, MediaItem $item): string
    {
        $returnTo = (string) $request->query('return_to', route('archive.photos.show', $item, false));

        return str_starts_with($returnTo, '/archive') ? $returnTo : route('archive.index', absolute: false);
    }

    private function editorReturnTo(Request $request, MediaItem $item): string
    {
        $returnTo = (string) $request->query('editor_return_to', route('archive.photos.editor', [
            'single_photo' => $item->id,
            'return_to' => route('archive.photos.show', $item, false),
        ], false));

        return str_starts_with($returnTo, '/archive/photo-editor') ? $returnTo : route('archive.photos.editor', [
            'single_photo' => $item->id,
            'return_to' => route('archive.photos.show', $item, false),
        ], false);
    }

    private function editorUrlWithSplit(string $editorReturnTo, MediaItem $source, MediaItem $split, string $returnTo): string
    {
        $parts = parse_url($editorReturnTo);
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        $query = [];
        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        if ($path !== '/archive/photo-editor') {
            $path = '/archive/photo-editor';
            $query = [];
        }
        if (isset($query['single_photo'])) {
            $query['single_photo'] = $source->id;
            unset($query['photo']);
        } else {
            $query['photo'] = $source->id;
        }
        $query['split_photo'] = $split->id;
        $query['return_to'] = $returnTo;

        return $path.'?'.http_build_query($query);
    }
}
