<x-layouts::app :title="$source->name">
    @if (session('status'))<div class="mx-auto mt-4 w-full max-w-6xl rounded-xl border border-emerald-700 p-4 text-emerald-100">{{ session('status') }}</div>@endif
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header>
            <a href="{{ route('archive.sources.index') }}" class="text-sm text-emerald-300">&larr; Back to sources</a>
            <p class="mt-4 text-xs font-semibold uppercase text-emerald-300">{{ $source->source_id }}</p>
            <h1 class="text-3xl font-semibold text-white">{{ $source->name }}</h1>
            <p class="text-zinc-400">{{ str($source->type->value)->replace('_', ' ')->title() }} &middot; {{ $source->physical_reference ?: 'No physical reference' }}</p>
            <p class="mt-3 max-w-3xl text-zinc-300">{{ $source->description ?: 'No description recorded.' }}</p>
        </header>
        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <h2 class="text-xl font-semibold text-white">Scan batches</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($source->scanBatches as $batch)
                        <div class="rounded-lg border border-zinc-700 p-4">
                            <p class="text-xs text-emerald-300">{{ $batch->scan_batch_id }}</p>
                            <p class="font-semibold text-white">{{ $batch->label }}</p>
                            <p class="text-sm text-zinc-400">{{ $batch->scanned_on?->format('j F Y') ?: 'Scan date unknown' }}</p>
                        </div>
                    @empty
                        <p class="text-zinc-400">No scan batches recorded.</p>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('archive.sources.scan-batches.store', $source) }}" class="mt-6 space-y-3 border-t border-zinc-700 pt-5">
                    @csrf
                    <h3 class="font-semibold text-white">Add scan batch</h3>
                    <input name="label" required maxlength="160" placeholder="Batch label" class="w-full rounded-lg bg-zinc-950 p-3">
                    <input type="date" name="scanned_on" class="w-full rounded-lg bg-zinc-950 p-3">
                    <textarea name="notes" maxlength="2000" rows="2" placeholder="Scan notes" class="w-full rounded-lg bg-zinc-950 p-3"></textarea>
                    <button class="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Create scan batch</button>
                </form>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <h2 class="text-xl font-semibold text-white">Linked approved photos</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($source->provenanceLinks as $link)
                        <a href="{{ route('archive.photos.show', $link->mediaItem) }}" class="block rounded-lg border border-zinc-700 p-4">
                            <p class="text-xs text-emerald-300">{{ $link->mediaItem->archive_id }}</p>
                            <p class="font-semibold text-white">{{ $link->mediaItem->title ?: 'Untitled archive photo' }}</p>
                            <p class="text-sm text-zinc-400">{{ $link->scanBatch?->scan_batch_id ?: 'No scan batch' }}</p>
                        </a>
                    @empty
                        <p class="text-zinc-400">No approved photos linked.</p>
                    @endforelse
                </div>
            </article>
        </section>
        <div class="rounded-xl border border-amber-700 p-4 text-amber-100">Source records describe provenance only. They do not move, rename, replace or expose preserved files.</div>
    </div>
</x-layouts::app>
