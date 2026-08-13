<x-layouts::app :title="$hiddenGallery ? __('Hidden Photos') : __('Private Archive')">
<x-archive-shell>
    @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
    <div data-archive-gallery data-context="{{ $selectionContext }}" data-hidden-gallery="{{ $hiddenGallery ? '1' : '0' }}" data-selection-url-template="{{ route('archive.selections.update', ['mediaItem' => '__PHOTO__']) }}" data-clear-url="{{ route('archive.selections.clear') }}" data-single-hide-template="{{ route('archive.photos.hide.form', ['mediaItem' => '__PHOTO__']) }}" data-initial-selected-count="{{ $selectedCount }}" data-initial-selected-ids='@json($selectedIds->values())' data-initial-selected-pages="{{ $selectedPageCount }}" data-current-page="{{ $photos->currentPage() }}">
        <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div><p class="text-sm font-medium text-emerald-300">{{ $hiddenGallery ? 'Preserved owner-managed records' : 'Access-filtered family archive' }}</p><h1 class="text-3xl font-semibold text-white">{{ $hiddenGallery ? 'Hidden Photos' : 'Photos' }}</h1><p class="mt-2 max-w-2xl text-zinc-400">{{ $hiddenGallery ? 'Hidden photos remain preserved with their albums, metadata, provenance, split lineage and files intact.' : 'Browse preservation-safe copies allowed by your role and family branch.' }}</p></div>
            <div class="flex flex-wrap items-center gap-3">
                @if(!$hiddenGallery)<button type="button" data-edit-toggle class="rounded-xl border border-emerald-700 px-4 py-3 text-sm font-semibold text-emerald-200">Edit photos</button>@endif
                <a href="{{ $hiddenGallery ? route('archive.index') : route('archive.photos.hidden') }}" class="rounded-xl border border-zinc-700 px-4 py-3 text-sm text-zinc-200">{{ $hiddenGallery ? 'Visible photos' : 'Hidden photos' }}</a>
                @if(auth()->user()?->role === 'owner')<a href="{{ route('archive.sources.index') }}" class="rounded-xl border border-zinc-700 px-4 py-3 text-sm text-emerald-300">Source provenance</a>@endif
                <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100"><strong>{{ $photos->total() }}</strong> {{ $hiddenGallery ? 'hidden' : 'approved' }} records</div>
            </div>
        </header>

        <section class="mt-6 flex flex-col gap-4 rounded-xl border border-zinc-700 bg-zinc-900 p-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="flex gap-2" aria-label="Photo ownership filter">
                <a href="{{ request()->fullUrlWithQuery(['scope' => 'all', 'page' => null]) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $scope === 'all' ? 'bg-emerald-500 text-zinc-950' : 'border border-zinc-700 text-zinc-300' }}">All {{ $hiddenGallery ? 'hidden' : 'photos' }}</a>
                <a href="{{ request()->fullUrlWithQuery(['scope' => 'mine', 'page' => null]) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $scope === 'mine' ? 'bg-emerald-500 text-zinc-950' : 'border border-zinc-700 text-zinc-300' }}">My uploads</a>
            </nav>
            <form method="POST" action="{{ route('archive.photos.preferences.update') }}" class="flex items-center gap-3">@csrf @method('PATCH')<input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}"><input type="hidden" name="previous_rows" value="{{ $rows }}"><input type="hidden" name="current_page" value="{{ $photos->currentPage() }}"><label for="photo-rows" class="text-sm text-zinc-400">Rows per page</label><select id="photo-rows" name="rows" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-white" onchange="this.form.submit()">@foreach([2, 4, 8, 16] as $option)<option value="{{ $option }}" @selected($rows === $option)>{{ $option }}</option>@endforeach</select></form>
        </section>

        <section class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($photos as $photo)
                @include('archive._photo-card', ['photo' => $photo, 'selectable' => $hiddenGallery || auth()->user()?->role === 'owner' || $photo->createdBy === auth()->id(), 'selected' => $selectedIds->contains($photo->mediaItemId), 'returnTo' => request()->getRequestUri()])
            @empty
                <div class="col-span-full rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center text-zinc-400">No {{ $hiddenGallery ? 'hidden' : 'approved' }} photos match this view.</div>
            @endforelse
        </section>
        @if($photos->hasPages())<div class="mt-6 rounded-xl border border-zinc-700 bg-zinc-900 p-4">{{ $photos->links() }}</div>@endif

        <div data-selection-toolbar class="fixed inset-x-3 bottom-3 z-40 mx-auto hidden max-w-4xl rounded-2xl border border-emerald-700 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur" role="region" aria-label="Photo selection actions">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><strong class="text-white"><span data-selected-count>{{ $selectedCount }}</span> selected across <span data-selected-pages>{{ $selectedPageCount }}</span> pages</strong><p class="text-xs text-zinc-400">Selection is preserved across pages and photo views.</p></div><div class="flex flex-wrap gap-2">
                <button type="button" data-select-page class="rounded-lg border border-zinc-600 px-3 py-2 text-sm text-zinc-200">Select this page</button><button type="button" data-clear-selection class="rounded-lg border border-zinc-600 px-3 py-2 text-sm text-zinc-200">Clear selection</button>
                @if($hiddenGallery)<form method="POST" action="{{ route('archive.photos.restore.batch') }}" data-processing-form>@csrf<button data-restore-action class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950" @disabled($selectedCount === 0)>Restore selected</button></form>
                @else<a href="{{ route('archive.photos.editor', ['return_to' => request()->getRequestUri()]) }}" data-edit-selected class="hidden rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Edit selected</a><a data-single-hide-action href="#" class="hidden rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-950">Hide photo</a><form method="POST" action="{{ route('archive.photos.hide.batch') }}" data-batch-hide-form data-processing-form class="hidden">@csrf<button class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-950">Hide selected photos</button></form><button type="button" data-exit-edit class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-200">Exit edit mode</button>@endif
            </div></div><p data-processing-message class="mt-3 hidden text-sm text-emerald-300">Processing your selected photos safely…</p>
        </div>
    </div>
</x-archive-shell>
</x-layouts::app>
