<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Browsing\Queries\ApprovedPhotoDetailQuery;
use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ArchiveBrowseController extends Controller
{
    public function index(Request $request, ApprovedPhotoGalleryQuery $query): View
    {
        return view('archive.index', ['photos' => $query->handle($request->user())]);
    }

    public function show(Request $request, MediaItem $mediaItem, ApprovedPhotoDetailQuery $query): View
    {
        $photo = $query->handle($request->user(), $mediaItem);
        abort_unless($photo !== null, 404);

        return view('archive.show', compact('photo'));
    }
}
