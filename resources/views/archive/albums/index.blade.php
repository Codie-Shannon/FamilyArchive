<x-layouts::app title="Family Albums">
    <x-archive-shell>
        @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif

        <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-300">One familiar way to explore</p>
                <h1 class="text-3xl font-semibold text-white">Albums</h1>
                <p class="mt-2 max-w-2xl text-zinc-400">Browse family photos by story, event, place, person or branch. The archive keeps the detailed records connected behind each album.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('public-discovery.map') }}" class="rounded-xl border border-zinc-700 px-4 py-3 text-sm font-semibold text-zinc-200 hover:bg-zinc-800">Explore map</a>
                @if($canManageAlbums)<a href="{{ route('archive.albums.create') }}" class="rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-zinc-950">Create album</a>@endif
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach([
                'album' => ['Family albums', 'Curated by trusted family'],
                'event' => ['Events', 'Weddings, reunions and moments'],
                'place' => ['Places', 'Homes, towns and journeys'],
                'person' => ['People', 'Photos connected to a person'],
                'branch' => ['Branches', 'Family lines together'],
            ] as $key => [$label, $description])
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                    <p class="text-2xl font-semibold text-white">{{ number_format($counts->get($key, 0)) }}</p>
                    <p class="font-semibold text-emerald-300">{{ $label }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $description }}</p>
                </article>
            @endforeach
        </section>

        <form class="flex gap-3 rounded-xl border border-zinc-700 bg-zinc-900 p-3" action="{{ route('archive.albums.index') }}">
            <input name="q" value="{{ $query }}" maxlength="100" placeholder="Find an album by person, place, event or branch" class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3 text-white placeholder:text-zinc-600">
            <button class="rounded-lg bg-emerald-500 px-5 font-semibold text-zinc-950">Find albums</button>
        </form>

        <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($albums as $album)
                <article class="overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-xl shadow-black/10">
                    <a href="{{ route('archive.albums.show', [$album->type->value, $album->stableId]) }}" class="block h-full">
                        <div class="aspect-[4/3] bg-zinc-950">
                            @if($album->coverVersionId)
                                <img src="{{ route('archive.derivatives.preview', $album->coverVersionId) }}" alt="Cover for {{ $album->name }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center bg-gradient-to-br from-emerald-950 to-zinc-950 text-5xl text-emerald-300">◇</div>
                            @endif
                        </div>
                        <div class="p-5">
                            <div class="flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wide">
                                <span class="text-emerald-300">{{ $album->type->label() }}</span>
                                <span class="text-zinc-500">{{ $album->photoCount }} {{ str('photo')->plural($album->photoCount) }}</span>
                            </div>
                            <h2 class="mt-2 text-xl font-semibold text-white">{{ $album->name }}</h2>
                            @if($album->subtitle)<p class="mt-1 text-sm text-zinc-300">{{ $album->subtitle }}</p>@endif
                            @if($album->description)<p class="mt-3 line-clamp-2 text-sm text-zinc-500">{{ $album->description }}</p>@endif
                        </div>
                    </a>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center">
                    <h2 class="text-lg font-semibold text-white">No matching albums yet</h2>
                    <p class="mt-2 text-zinc-400">Albums appear when reviewed photos are connected to family knowledge or gathered into a family album.</p>
                </div>
            @endforelse
        </section>
    </x-archive-shell>
</x-layouts::app>
