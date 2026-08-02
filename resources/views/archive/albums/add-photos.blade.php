<x-layouts::app :title="'Add photos to '.$album->name">
    <x-archive-shell>
        @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif

        <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <a href="{{ route('archive.albums.show', ['album', $album->collection_id]) }}" class="text-sm font-semibold text-emerald-300">← {{ $album->name }}</a>
                <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-zinc-500">Batch album update</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Add photos to album</h1>
                <p class="mt-2 max-w-2xl text-zinc-400">Search the approved archive, select the photos that belong together, then add them in one step. Originals and existing archive records remain unchanged.</p>
            </div>
            <span class="rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-3 text-sm text-zinc-300"><strong class="text-white">{{ $album->mediaItems()->count() }}</strong> currently in album</span>
        </header>

        <form method="GET" action="{{ route('archive.albums.photos.add', $album) }}" class="flex flex-col gap-3 rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:flex-row">
            <input name="q" value="{{ $query }}" maxlength="100" placeholder="Search by title, archive ID, description or story" class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white placeholder:text-zinc-600">
            <button class="rounded-lg border border-zinc-600 px-5 py-3 font-semibold text-white hover:bg-zinc-800">Search photos</button>
            @if(filled($query))<a href="{{ route('archive.albums.photos.add', $album) }}" class="rounded-lg px-4 py-3 text-center text-sm font-semibold text-zinc-400 hover:text-white">Clear</a>@endif
        </form>

        <form method="POST" action="{{ route('archive.albums.photos.attach', $album) }}" class="space-y-5">
            @csrf
            @error('photo_ids')<p class="rounded-xl border border-red-700 bg-red-950/30 p-4 text-red-200">{{ $message }}</p>@enderror

            <div class="flex flex-col gap-3 rounded-xl border border-emerald-800 bg-emerald-950/20 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-white">Choose up to 100 photos</p>
                    <p class="text-sm text-zinc-400">Only approved photos you can access are shown. Photos already in this album are omitted.</p>
                </div>
                <button class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950">Add selected photos</button>
            </div>

            <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse($photos as $photo)
                    <label class="group relative cursor-pointer overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-xl shadow-black/10 has-[:checked]:border-emerald-400 has-[:checked]:ring-2 has-[:checked]:ring-emerald-400/40">
                        <input type="checkbox" name="photo_ids[]" value="{{ $photo->mediaItemId }}" class="peer absolute right-3 top-3 z-10 h-6 w-6 rounded border-zinc-500 bg-zinc-950 text-emerald-500 focus:ring-emerald-500">
                        <div class="aspect-[4/3] bg-zinc-950">
                            @if($photo->thumbnailVersionId)
                                <img src="{{ route('archive.derivatives.preview', $photo->thumbnailVersionId) }}" alt="Private thumbnail for {{ $photo->archiveId }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-zinc-500"><span class="text-4xl">◇</span><strong class="text-zinc-300">Preview unavailable</strong></div>
                            @endif
                        </div>
                        <div class="p-4 peer-checked:bg-emerald-950/30">
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-300">{{ $photo->archiveId }}</p>
                            <p class="mt-1 font-semibold text-white">{{ $photo->title }}</p>
                        </div>
                    </label>
                @empty
                    <div class="col-span-full rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center">
                        <h2 class="font-semibold text-white">No eligible photos found</h2>
                        <p class="mt-2 text-zinc-400">Try a different search, or return to the album. Every accessible photo may already be included.</p>
                    </div>
                @endforelse
            </section>

            @if($photos->hasPages())<div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">{{ $photos->withQueryString()->links() }}</div>@endif
        </form>
    </x-archive-shell>
</x-layouts::app>
