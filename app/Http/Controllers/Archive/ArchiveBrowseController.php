<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Archive\Models\UserArchivePreference;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Browsing\Queries\ApprovedPhotoDetailQuery;
use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ArchiveBrowseController extends Controller
{
    public function index(Request $request, ApprovedPhotoGalleryQuery $query, ArchiveSelectionManager $selections): View
    {
        return $this->gallery($request, $query, $selections, false);
    }

    public function hidden(Request $request, ApprovedPhotoGalleryQuery $query, ArchiveSelectionManager $selections): View
    {
        return $this->gallery($request, $query, $selections, true);
    }

    public function show(Request $request, MediaItem $mediaItem, ApprovedPhotoDetailQuery $query): View
    {
        $photo = $query->handle($request->user(), $mediaItem);
        abort_unless($photo !== null, 404);

        $returnTo = (string) $request->query('return_to', '');
        if (! str_starts_with($returnTo, '/archive')) {
            $returnTo = route('archive.index', absolute: false);
        }

        return view('archive.show', compact('photo', 'returnTo'));
    }

    private function gallery(Request $request, ApprovedPhotoGalleryQuery $query, ArchiveSelectionManager $selections, bool $hidden): View
    {
        $user = $request->user();
        $scope = $request->string('scope')->value() === 'mine' ? 'mine' : 'all';
        $rows = UserArchivePreference::query()->where('user_id', $user->id)->value('photo_gallery_rows') ?? 4;
        $rows = in_array((int) $rows, [2, 4, 8, 16], true) ? (int) $rows : 4;
        $context = $hidden ? 'photos:hidden' : 'photos:visible';
        $selectedIds = $selections->ids($user, $context);
        $selectionSummary = $selections->summary($user, $context);

        return view('archive.index', [
            'photos' => $query->handle(
                $user,
                $rows * 4,
                createdBy: $scope === 'mine' ? $user->id : null,
                hidden: $hidden,
            )->withQueryString(),
            'hiddenGallery' => $hidden,
            'scope' => $scope,
            'rows' => $rows,
            'selectionContext' => $context,
            'selectedIds' => $selectedIds,
            'selectedCount' => $selectedIds->count(),
            'selectedPageCount' => $selectionSummary['page_count'],
        ]);
    }
}
