<x-layouts::app title="Search Family Archive">
    <x-archive-shell>
        <header>
            <p class="text-sm font-semibold text-emerald-300">Photos and family stories together</p>
            <h1 class="text-3xl font-semibold text-white">Search</h1>
            <p class="mt-2 text-zinc-400">Find approved photos and albums by title, story, person, place, event or family branch.</p>
        </header>

        <form class="flex gap-3 rounded-xl border border-zinc-700 bg-zinc-900 p-3">
            <input name="q" value="{{ $query }}" maxlength="100" placeholder="Try a surname, home, town, year or event" class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white placeholder:text-zinc-600">
            <button class="rounded-lg bg-emerald-500 px-5 font-semibold text-zinc-950">Search</button>
        </form>

        @if($query === '')
            <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-8 text-center">
                <h2 class="text-xl font-semibold text-white">Search the archive in one place</h2>
                <p class="mt-2 text-zinc-400">You no longer need to decide whether something belongs under people, places, events or branches first.</p>
            </section>
        @else
            <section>
                <div class="flex items-center justify-between"><h2 class="text-xl font-semibold text-white">Albums</h2><span class="text-sm text-zinc-500">{{ $albums->count() }} found</span></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($albums->take(9) as $album)
                        <a href="{{ route('archive.albums.show', [$album->type->value, $album->stableId]) }}" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 hover:border-emerald-700">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">{{ $album->type->label() }} · {{ $album->photoCount }} {{ str('photo')->plural($album->photoCount) }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-white">{{ $album->name }}</h3>
                            @if($album->subtitle)<p class="mt-1 text-sm text-zinc-400">{{ $album->subtitle }}</p>@endif
                        </a>
                    @empty
                        <p class="text-zinc-500 sm:col-span-2 lg:col-span-3">No accessible albums matched.</p>
                    @endforelse
                </div>
            </section>

            <section>
                <div class="flex items-center justify-between"><h2 class="text-xl font-semibold text-white">Photos</h2><span class="text-sm text-zinc-500">{{ $photos?->total() ?? 0 }} found</span></div>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse($photos ?? [] as $photo)
                        @include('archive._photo-card', ['photo' => $photo])
                    @empty
                        <p class="text-zinc-500 sm:col-span-2 lg:col-span-4">No accessible photos matched.</p>
                    @endforelse
                </div>
                @if($photos?->hasPages())<div class="mt-5 rounded-xl border border-zinc-700 bg-zinc-900 p-4">{{ $photos->appends(['q' => $query])->links() }}</div>@endif
            </section>
        @endif

        <aside class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-5 text-sm text-emerald-100">Search applies the same family, branch, privacy and reviewed-record boundaries as the archive itself.</aside>
    </x-archive-shell>
</x-layouts::app>
