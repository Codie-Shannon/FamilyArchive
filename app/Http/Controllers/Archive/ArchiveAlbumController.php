<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Archive\Models\UserArchivePreference;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Domain\Knowledge\Enums\ArchiveAlbumType;
use App\Domain\Knowledge\Models\CuratedCollection;
use App\Domain\Knowledge\Services\ArchiveAlbumExplorer;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ArchiveAlbumController extends Controller
{
    public function index(Request $request, ArchiveAlbumExplorer $albums): View
    {
        $query = mb_substr(trim((string) $request->string('q')), 0, 100);
        $items = $albums->browse($request->user(), $query);

        return view('archive.albums.index', [
            'albums' => $items,
            'query' => $query,
            'counts' => $items->countBy(fn ($album): string => $album->type->value),
            'canManageAlbums' => $request->user()->canManageTrustedIntake(),
        ]);
    }

    public function show(
        Request $request,
        string $type,
        string $stableId,
        ArchiveAlbumExplorer $albums,
        ApprovedPhotoGalleryQuery $gallery,
    ): View {
        $albumType = ArchiveAlbumType::tryFrom($type);
        abort_unless($albumType instanceof ArchiveAlbumType, 404);
        $album = $albums->find($request->user(), $albumType, $stableId);
        abort_unless($album !== null, 404);

        $curated = $albumType === ArchiveAlbumType::Curated
            ? CuratedCollection::query()->where('collection_id', $stableId)->firstOrFail()
            : null;
        $canManage = $curated instanceof CuratedCollection && $request->user()->canManageTrustedIntake();

        return view('archive.albums.show', [
            'album' => $album,
            'photos' => $gallery->handle($request->user(), 12, mediaItemIds: $album->mediaItemIds),
            'curated' => $curated,
            'canManage' => $canManage,
        ]);
    }

    public function create(): View
    {
        return view('archive.albums.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $album = CuratedCollection::query()->create([
            'collection_id' => 'ALB-'.Str::upper((string) Str::ulid()),
            'name' => $validated['name'],
            'description' => filled($validated['description'] ?? null) ? $validated['description'] : null,
            'is_published' => true,
            'curated_by' => $request->user()->id,
        ]);

        return redirect()->route('archive.albums.photos.add', $album)
            ->with('status', 'Album created. Choose the approved photos to add.');
    }

    public function addPhotos(
        Request $request,
        CuratedCollection $curatedCollection,
        ApprovedPhotoGalleryQuery $gallery,
        ArchiveSelectionManager $selections,
    ): View {
        $query = mb_substr(trim((string) $request->string('q')), 0, 100);
        $rows = UserArchivePreference::query()->where('user_id', $request->user()->id)->value('photo_gallery_rows') ?? 4;
        $rows = in_array((int) $rows, [2, 4, 8, 16], true) ? (int) $rows : 4;
        $context = 'album:'.$curatedCollection->id;
        $selectedIds = $selections->ids($request->user(), $context);
        $selectionSummary = $selections->summary($request->user(), $context);

        return view('archive.albums.add-photos', [
            'album' => $curatedCollection,
            'photos' => $gallery->handle(
                $request->user(),
                $rows * 4,
                search: $query,
                excludedCuratedCollectionId: $curatedCollection->id,
            )->withQueryString(),
            'query' => $query,
            'rows' => $rows,
            'selectionContext' => $context,
            'selectedIds' => $selectedIds,
            'selectedCount' => $selectedIds->count(),
            'selectedPageCount' => $selectionSummary['page_count'],
        ]);
    }

    public function attachBatch(
        Request $request,
        CuratedCollection $curatedCollection,
        ArchiveAccess $access,
        ArchiveSelectionManager $selections,
    ): RedirectResponse {
        $validated = $request->validate([
            'photo_ids' => ['nullable', 'array', 'min:1'],
            'photo_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $photoIds = $validated['photo_ids'] ?? $selections->ids($request->user(), 'album:'.$curatedCollection->id)->all();
        abort_unless(is_array($photoIds) && count($photoIds) > 0, 422);

        $requestedIds = collect(array_map(
            static fn (mixed $id): int => (int) $id,
            $photoIds,
        ))->unique()->values();
        $visibleIds = $access->scopeVisible(MediaItem::query()
            ->whereKey($requestedIds)
            ->where('media_type', MediaType::Photo)
            ->where('review_status', MediaReviewStatus::Approved)
            ->whereNotNull('approved_at'), $request->user())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
        abort_unless($visibleIds->count() === $requestedIds->count(), 404);

        $nextPosition = (int) DB::table('curated_collection_media')
            ->where('curated_collection_id', $curatedCollection->id)
            ->max('position') + 1;
        $membership = [];
        foreach ($visibleIds as $offset => $mediaId) {
            $membership[$mediaId] = [
                'added_by' => $request->user()->id,
                'position' => $nextPosition + $offset,
            ];
        }
        $curatedCollection->mediaItems()->syncWithoutDetaching($membership);
        $selections->clear($request->user(), 'album:'.$curatedCollection->id);

        return redirect()->route('archive.albums.show', [ArchiveAlbumType::Curated->value, $curatedCollection->collection_id])
            ->with('status', $visibleIds->count().' '.str('photo')->plural($visibleIds->count()).' added to the album.');
    }

    public function detach(CuratedCollection $curatedCollection, MediaItem $mediaItem): RedirectResponse
    {
        $curatedCollection->mediaItems()->detach($mediaItem->id);

        return back()->with('status', 'Photo removed from the album. The archive photo itself was not changed.');
    }
}
