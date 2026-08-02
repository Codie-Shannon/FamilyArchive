<x-layouts::app title="Reviewed Events">
    <x-archive-shell>

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Reviewed events</h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-300">Explore accepted historical events without manufacturing date precision.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('archive.locations.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-600 dark:text-white">Locations</a>
                @if($canCurate)<a href="{{ route('archive.events.create') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-black">Add reviewed event</a>@endif
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-2">
            @forelse($events as $event)
                <a href="{{ route('archive.events.show', $event) }}" class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="font-mono text-xs text-zinc-500">{{ $event->event_id }}</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-950 dark:text-white">{{ $event->name }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $datePresenter->display($event) }} · {{ str($event->date_confidence->value)->headline() }} confidence</p>
                    @if($event->location)<p class="mt-1 text-sm text-zinc-500">{{ $locationPresenter->browseLabel($event->location) }}</p>@endif
                    <p class="mt-4 text-xs text-zinc-500">
                        {{ $event->media_items_count }} media
                        @if ($canCurate)
                            · {{ $event->provenance_links_count }} sources
                        @endif
                    </p>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300 md:col-span-2">
                    No reviewed events yet. Add the first event from human-reviewed source evidence.
                </div>
            @endforelse
        </section>

        {{ $events->links() }}
    </x-archive-shell>
</x-layouts::app>
