<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\LinkReviewedMediaToEvent;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\StoreEventMediaRequest;
use Illuminate\Http\RedirectResponse;

final class EventMediaController extends Controller
{
    public function store(
        StoreEventMediaRequest $request,
        ArchiveEvent $archiveEvent,
        LinkReviewedMediaToEvent $action
    ): RedirectResponse {
        $media = MediaItem::query()->findOrFail(
            (int) $request->validated('media_item_id')
        );

        $action->handle(
            $archiveEvent,
            $media,
            (string) $request->validated('confidence'),
            (string) $request->validated('source_note'),
            (string) $request->validated('change_reason'),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.events.show', $archiveEvent)
            ->with('status', 'Reviewed media link attached to event.');
    }
}
