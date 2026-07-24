<x-layouts::app title="Source provenance">
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('archive.index') }}" class="text-sm text-emerald-300">&larr; Back to archive</a>
                <h1 class="mt-4 text-3xl font-semibold text-white">Source collections</h1>
                <p class="text-zinc-400">Physical provenance records remain separate from preserved media.</p>
            </div>
            <a href="{{ route('archive.sources.create') }}" class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Create source</a>
        </header>
        <section class="grid gap-4 md:grid-cols-2">
            @forelse ($sources as $source)
                <a href="{{ route('archive.sources.show', $source) }}" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-xs font-semibold uppercase text-emerald-300">{{ $source->source_id }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">{{ $source->name }}</h2>
                    <p class="mt-2 text-sm text-zinc-400">{{ str($source->type->value)->replace('_', ' ')->title() }}</p>
                    <p class="mt-4 text-sm text-zinc-300">{{ $source->scan_batches_count }} scan batches &middot; {{ $source->media_items_count }} linked photos</p>
                </a>
            @empty
                <p class="rounded-xl border border-zinc-700 p-6 text-zinc-400">No source collections recorded.</p>
            @endforelse
        </section>
    </div>
</x-layouts::app>
