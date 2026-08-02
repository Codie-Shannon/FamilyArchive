<x-layouts::app :title="$album->name">
    <x-archive-shell>
        @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif

        <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <a href="{{ route('archive.albums.index') }}" class="text-sm font-semibold text-emerald-300">← All albums</a>
                <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-zinc-500">{{ $album->type->label() }}</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">{{ $album->name }}</h1>
                @if($album->subtitle)<p class="mt-1 text-lg text-zinc-300">{{ $album->subtitle }}</p>@endif
                @if($album->description)<p class="mt-3 max-w-3xl text-zinc-400">{{ $album->description }}</p>@endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-3 text-sm text-zinc-300"><strong class="text-white">{{ $album->photoCount }}</strong> {{ str('photo')->plural($album->photoCount) }}</span>
                @if($album->contextUrl)<a href="{{ $album->contextUrl }}" class="rounded-xl border border-zinc-700 px-4 py-3 text-sm font-semibold text-emerald-300">View details</a>@endif
                @if($canManage && $curated)<a href="{{ route('archive.albums.photos.add', $curated) }}" class="rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-zinc-950">Add photos</a>@endif
            </div>
        </header>

        <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($photos as $photo)
                <div class="relative">
                    @include('archive._photo-card', ['photo' => $photo])
                    @if($canManage && $curated)
                        <form method="POST" action="{{ route('archive.albums.photos.detach', [$curated, $photo->mediaItemId]) }}" class="absolute right-3 top-3">
                            @csrf @method('DELETE')
                            <button aria-label="Remove {{ $photo->title }} from this album" class="rounded-lg bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-white shadow">Remove</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center text-zinc-400">No accessible approved photos are connected to this album yet.</div>
            @endforelse
        </section>
        @if($photos->hasPages())<div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">{{ $photos->links() }}</div>@endif
    </x-archive-shell>
</x-layouts::app>
