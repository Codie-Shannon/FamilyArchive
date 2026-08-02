<x-layouts::app title="Reviewed Location">
    <x-archive-shell>
        @if(session('status'))<div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="font-mono text-xs text-zinc-500">{{ $location->location_id }}</p><h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">{{ $presenter->browseLabel($location) }}</h1><p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $presenter->browseDetail($location) }}</p></div>
            @if($canCurate)<a href="{{ route('archive.locations.edit', $location) }}" class="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Review location</a>@endif
        </header>

        @if($location->is_sensitive)<aside class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">Sensitive location: its stored label and detailed locality are withheld from browsing.</aside>@endif

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Precision</p><p class="mt-2 font-semibold">{{ str($location->precision->value)->headline() }}</p></article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Confidence</p><p class="mt-2 font-semibold">{{ str($location->confidence->value)->headline() }}</p></article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Reviewed status</p><p class="mt-2 font-semibold">Accepted</p></article>
        </section>

        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><h2 class="text-xl font-semibold">Reviewed events here</h2><div class="mt-4 space-y-3">@forelse($location->events as $event)<a class="block rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" href="{{ route('archive.events.show', $event) }}"><strong>{{ $event->name }}</strong><span class="ml-2 font-mono text-xs text-zinc-500">{{ $event->event_id }}</span></a>@empty<p class="text-zinc-500">No reviewed events are linked to this location.</p>@endforelse</div></section>

        @if($canCurate)<section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><h2 class="text-xl font-semibold">Immutable revision evidence</h2><div class="mt-4 space-y-3">@foreach($location->revisions as $revision)<article class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-900"><strong>Revision {{ $revision->revision_number }}</strong><p class="text-zinc-500">{{ implode(', ', $revision->changed_fields) }} · {{ $revision->actor->name }}</p></article>@endforeach</div></section>@endif
    </x-archive-shell>
</x-layouts::app>
