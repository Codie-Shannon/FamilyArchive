<x-layouts::app title="Reviewed Locations">
    <x-archive-shell>

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Reviewed locations</h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-300">Browse normalized places without exposing sensitive location precision.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('public-discovery.map') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-600 dark:text-white">Public map</a>
                <a href="{{ route('archive.events.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-600 dark:text-white">Events</a>
                @if($canCurate)<a href="{{ route('archive.locations.create') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-black">Add reviewed location</a>@endif
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse($locations as $location)
                <a href="{{ route('archive.locations.show', $location) }}" class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="font-mono text-xs text-zinc-500">{{ $location->location_id }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ $presenter->browseLabel($location) }}</h2>
                    @if($presenter->browseSubtitle($location))<p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $presenter->browseSubtitle($location) }}</p>@endif
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $presenter->browseDetail($location) }}</p>
                    <p class="mt-3 text-xs text-zinc-500">{{ $location->events_count }} reviewed events</p>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300 md:col-span-2">
                    No reviewed locations yet. Add the first location from human-reviewed source evidence.
                </div>
            @endforelse
        </section>

        {{ $locations->links() }}
    </x-archive-shell>
</x-layouts::app>
