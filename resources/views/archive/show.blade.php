<x-layouts::app :title="$photo->title">
@if(session('status'))<div class="mx-auto mt-4 w-full max-w-6xl rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-8">
    <header><div class="flex flex-wrap items-center justify-between gap-3"><a href="{{ route('archive.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-300 hover:text-emerald-200">← Back to private archive</a><div class="flex gap-3"><a href="{{ route('archive.photos.metadata.edit', $photo->mediaItemId) }}" class="text-sm font-medium text-emerald-300">Edit metadata</a><a href="{{ route('archive.photos.metadata.history', $photo->mediaItemId) }}" class="text-sm font-medium text-emerald-300">Revision history</a></div></div><p class="mt-5 text-xs font-semibold uppercase tracking-wider text-emerald-300">{{ $photo->archiveId }}</p><h1 class="mt-1 text-3xl font-semibold text-white">{{ $photo->title }}</h1></header>
    <section class="overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-950">
        @if($photo->webDisplayVersionId)
            <img src="{{ route('archive.derivatives.preview', $photo->webDisplayVersionId) }}" alt="Private web display for {{ $photo->archiveId }}" class="max-h-[70vh] w-full object-contain">
        @else
            <div class="flex min-h-[420px] flex-col items-center justify-center gap-4 p-10 text-center"><span class="text-6xl text-zinc-600">◇</span><h2 class="text-2xl font-semibold text-white">Web display unavailable</h2><p class="max-w-lg text-zinc-400">This approved record has a {{ str_replace('_', ' ', $photo->webDisplayStatus) }} state. The archive will not substitute the original or thumbnail and browsing causes no generation side effect.</p></div>
        @endif
    </section>
    <section class="grid gap-5 lg:grid-cols-2">
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Approved descriptive metadata</h2><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-zinc-500">Description</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['description'] ?: 'No approved description recorded.' }}</dd></div><div><dt class="text-zinc-500">Story</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['story'] ?: 'No approved story recorded.' }}</dd></div><div class="grid grid-cols-2 gap-4"><div><dt class="text-zinc-500">Date</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['date'] }}</dd></div><div><dt class="text-zinc-500">Representation</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['date_precision'] }}</dd></div><div><dt class="text-zinc-500">Confidence</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['date_confidence'] }}</dd></div><div><dt class="text-zinc-500">Review state</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['date_review_state'] }}</dd></div></div><div><dt class="text-zinc-500">Date source</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['date_source_note'] ?: 'No date source recorded.' }}</dd></div><div><dt class="text-zinc-500">Date reasoning</dt><dd class="mt-1 text-zinc-200">{{ $photo->metadata['date_reason'] ?: 'No date reasoning recorded.' }}</dd></div></dl></article>
        <article class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-6"><h2 class="text-xl font-semibold text-white">Preservation summary</h2><dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><dt class="text-zinc-400">Archive record</dt><dd class="text-emerald-200">approved</dd><dt class="text-zinc-400">Preferred original</dt><dd class="text-emerald-200">{{ $photo->originalStatus }}</dd><dt class="text-zinc-400">Web display</dt><dd>{{ str_replace('_', ' ', $photo->webDisplayStatus) }}</dd><dt class="text-zinc-400">Thumbnail</dt><dd>{{ str_replace('_', ' ', $photo->thumbnailStatus) }}</dd><dt class="text-zinc-400">Lineage</dt><dd>derived from verified preferred original</dd><dt class="text-zinc-400">Recipe</dt><dd>{{ $photo->recipeLabel }}</dd></dl></article>
    </section>
    <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-semibold text-white">Source provenance</h2><p class="text-sm text-zinc-400">Reviewed links to collections, physical containers and scan batches.</p></div><a href="{{ route('archive.sources.index') }}" class="text-sm text-emerald-300">Manage source collections</a></div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @forelse ($photo->provenance as $link)
                <article class="rounded-lg border border-zinc-700 p-4"><p class="text-xs text-emerald-300">{{ $link['source_id'] }}</p><h3 class="font-semibold text-white">{{ $link['source_name'] }}</h3><p class="text-sm text-zinc-400">{{ str($link['source_type'])->replace('_', ' ')->title() }} &middot; {{ $link['scan_batch_id'] ?: 'No scan batch' }}</p><p class="mt-2 text-sm text-zinc-300">{{ $link['note'] ?: 'No attachment note.' }}</p><form method="POST" action="{{ route('archive.photos.provenance.destroy', [$photo->mediaItemId, $link['id']]) }}" class="mt-4 space-y-2">@csrf @method('DELETE')<input type="hidden" name="expected_metadata_revision" value="{{ $photo->metadata['metadata_revision'] ?? '' }}"><input name="change_reason" required minlength="5" maxlength="500" placeholder="Reason for removing link" class="w-full rounded bg-zinc-950 p-2 text-sm"><button class="text-sm text-red-300">Remove with revision</button></form></article>
            @empty
                <p class="text-zinc-400">No source provenance linked.</p>
            @endforelse
        </div>
        @if (count($photo->availableSources) > 0)
            <form method="POST" action="{{ route('archive.photos.provenance.store', $photo->mediaItemId) }}" class="mt-6 grid gap-4 border-t border-zinc-700 pt-5 md:grid-cols-2">
                @csrf
                <input type="hidden" name="expected_metadata_revision" value="{{ $photo->metadata['metadata_revision'] ?? '' }}">
                <label class="text-sm text-zinc-300">Source<select name="source_collection_id" required class="mt-2 w-full rounded bg-zinc-950 p-3">@foreach ($photo->availableSources as $source)<option value="{{ $source['id'] }}">{{ $source['source_id'] }} - {{ $source['source_name'] }}</option>@endforeach</select></label>
                <label class="text-sm text-zinc-300">Scan batch (optional)<select name="scan_batch_id" class="mt-2 w-full rounded bg-zinc-950 p-3"><option value="">No scan batch</option>@foreach ($photo->availableBatches as $batch)<option value="{{ $batch['id'] }}">{{ $batch['scan_batch_id'] }} - {{ $batch['label'] }}</option>@endforeach</select></label>
                <label class="text-sm text-zinc-300">Attachment note<textarea name="note" maxlength="2000" rows="2" class="mt-2 w-full rounded bg-zinc-950 p-3"></textarea></label>
                <label class="text-sm text-zinc-300">Revision reason<textarea name="change_reason" required minlength="5" maxlength="500" rows="2" class="mt-2 w-full rounded bg-zinc-950 p-3"></textarea></label>
                <button class="w-fit rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Attach provenance</button>
            </form>
        @endif
    </section>
    <section class="rounded-xl border border-amber-700 bg-amber-950/25 p-5 text-sm text-amber-100"><strong>Original privacy preserved:</strong> this page contains no original filename, storage path, hash, intake identifier, download route or mutation control.</section>
</div>
</x-layouts::app>
