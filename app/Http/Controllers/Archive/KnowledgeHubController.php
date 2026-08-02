<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Domain\Knowledge\Services\ArchiveAlbumExplorer;
use App\Domain\Knowledge\Services\ArchiveKnowledgeExplorer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class KnowledgeHubController extends Controller
{
    public function __invoke(
        Request $request,
        ArchiveKnowledgeExplorer $explorer,
        ArchiveAlbumExplorer $albums,
        ApprovedPhotoGalleryQuery $photos,
    ): View {
        $query = (string) $request->string('q');

        return view('archive.knowledge', [
            'counts' => $explorer->counts($request->user()),
            'query' => $query,
            'results' => $explorer->search($query, $request->user()),
            'albums' => $albums->browse($request->user(), $query),
            'photos' => $query === '' ? null : $photos->handle($request->user(), 8, search: $query),
            'canCurate' => $request->user()->isArchiveAdministrator(),
        ]);
    }
}
