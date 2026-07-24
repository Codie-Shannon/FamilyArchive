<x-layouts::app :title="'Revision '.$revision->revision_number">
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header>
            <a href="{{ route('archive.photos.metadata.history', $mediaItem) }}" class="text-sm text-emerald-300">&larr; Back to revision history</a>
            <p class="mt-5 text-xs uppercase text-emerald-300">{{ $mediaItem->archive_id }}</p>
            <h1 class="text-3xl font-semibold text-white">Revision {{ $revision->revision_number }}</h1>
            <p class="text-zinc-400">{{ $revision->actor->name }} &middot; {{ $revision->created_at->format('j M Y, g:i a') }}</p>
        </header>
        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <h2 class="font-semibold text-white">Reason</h2>
            <p class="mt-2">{{ $revision->change_reason }}</p>
        </section>
        @foreach ($revision->changed_fields as $field)
            <section class="grid gap-4 md:grid-cols-2">
                <article class="rounded-xl border border-red-900 p-5">
                    <h2 class="uppercase text-red-300">{{ str($field)->replace('_', ' ') }} before</h2>
                    <pre class="mt-3 whitespace-pre-wrap font-sans">{{ is_array($revision->before_values[$field] ?? null) ? json_encode($revision->before_values[$field], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ($revision->before_values[$field] ?? 'Empty value') }}</pre>
                </article>
                <article class="rounded-xl border border-emerald-900 p-5">
                    <h2 class="uppercase text-emerald-300">{{ str($field)->replace('_', ' ') }} after</h2>
                    <pre class="mt-3 whitespace-pre-wrap font-sans">{{ is_array($revision->after_values[$field] ?? null) ? json_encode($revision->after_values[$field], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ($revision->after_values[$field] ?? 'Empty value') }}</pre>
                </article>
            </section>
        @endforeach
        <div class="rounded-xl border border-amber-700 p-4 text-amber-100">This revision is immutable. No update, delete, revert, rewrite, download or storage control exists.</div>
    </div>
</x-layouts::app>
