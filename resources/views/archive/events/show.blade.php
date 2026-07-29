<x-layouts::app title="Reviewed Event">
    <main class="mx-auto max-w-6xl space-y-6 p-6">
        @if(session('status'))<div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="font-mono text-xs text-zinc-500">{{ $event->event_id }}</p><h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">{{ $event->name }}</h1><p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $datePresenter->display($event) }} · {{ str($event->date_confidence->value)->headline() }} confidence</p></div>
            <a href="{{ route('archive.events.edit', $event) }}" class="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Review event</a>
        </header>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Type</p><p class="mt-2 font-semibold">{{ str($event->type->value)->headline() }}</p></article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Location</p>@if($event->location)<a class="mt-2 block font-semibold" href="{{ route('archive.locations.show', $event->location) }}">{{ $locationPresenter->browseLabel($event->location) }}</a><p class="font-mono text-xs text-zinc-500">{{ $event->location->location_id }}</p><p class="text-sm text-zinc-500">{{ $locationPresenter->browseDetail($event->location) }}</p>@else<p class="mt-2 text-zinc-500">No reviewed location</p>@endif</article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Revision</p><p class="mt-2 font-semibold">{{ $event->metadata_revision }}</p></article>
        </section>

        @if($event->description)<section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><h2 class="text-xl font-semibold">Reviewed description</h2><p class="mt-3 whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $event->description }}</p></section>@endif

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <h2 class="text-xl font-semibold">Source provenance</h2>
                <div class="mt-4 space-y-3">@forelse($event->provenanceLinks as $link)<a href="{{ route('archive.sources.show', $link->sourceCollection) }}" class="block rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><strong>{{ $link->sourceCollection->name }}</strong><p class="font-mono text-xs text-zinc-500">{{ $link->sourceCollection->source_id }}@if($link->scanBatch) · {{ $link->scanBatch->scan_batch_id }}@endif</p><p class="mt-1 text-sm text-zinc-500">{{ $link->note }}</p></a>@empty<p class="text-zinc-500">No reviewed source is linked yet.</p>@endforelse</div>
                @if($sources->isNotEmpty())
                    <form method="POST" action="{{ route('archive.events.provenance.store', $event) }}" class="mt-5 space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">@csrf<input type="hidden" name="expected_metadata_revision" value="{{ $event->metadata_revision }}"><select name="source_collection_id" required class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="">Select source</option>@foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->source_id }} · {{ $source->name }}</option>@endforeach</select><select name="scan_batch_id" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="">No scan batch</option>@foreach($sources as $source)@foreach($source->scanBatches as $batch)<option value="{{ $batch->id }}">{{ $source->source_id }} · {{ $batch->label }}</option>@endforeach @endforeach</select><textarea name="note" rows="2" placeholder="Reviewed provenance note" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></textarea><textarea name="change_reason" required rows="2" placeholder="Reason for attaching this source" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></textarea><button class="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Attach source</button></form>
                @endif
            </article>

            <article class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <h2 class="text-xl font-semibold">Reviewed archive media</h2>
                <div class="mt-4 space-y-3">@forelse($event->mediaItems as $media)<a href="{{ route('archive.photos.show', $media) }}" class="block rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><strong>{{ $media->archive_id }}</strong><p class="text-sm text-zinc-500">{{ $media->title ?: 'Untitled approved media' }}</p><p class="mt-1 text-xs text-zinc-500">{{ str($media->pivot->confidence)->headline() }} · {{ $media->pivot->source_note }}</p></a>@empty<p class="text-zinc-500">No approved media is linked yet.</p>@endforelse</div>
                @if($availableMedia->isNotEmpty())
                    <form method="POST" action="{{ route('archive.events.media.store', $event) }}" class="mt-5 space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">@csrf<input type="hidden" name="expected_metadata_revision" value="{{ $event->metadata_revision }}"><select name="media_item_id" required class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="">Select approved media</option>@foreach($availableMedia as $media)<option value="{{ $media->id }}">{{ $media->archive_id }} · {{ $media->title ?: 'Untitled' }}</option>@endforeach</select><select name="confidence" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(\App\Domain\Media\Enums\StructuredDateConfidence::cases() as $case)<option value="{{ $case->value }}">{{ str($case->value)->headline() }}</option>@endforeach</select><textarea name="source_note" required rows="2" placeholder="Evidence for this media link" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></textarea><textarea name="change_reason" required rows="2" placeholder="Reason for attaching this media" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></textarea><button class="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Attach media</button></form>
                @endif
            </article>
        </section>

        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><h2 class="text-xl font-semibold">Immutable revision evidence</h2><div class="mt-4 space-y-3">@foreach($event->revisions as $revision)<article class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-900"><strong>Revision {{ $revision->revision_number }}</strong><p class="text-zinc-500">{{ implode(', ', $revision->changed_fields) }} · {{ $revision->actor->name }}</p><p class="mt-1">{{ $revision->change_reason }}</p></article>@endforeach</div></section>

        <aside class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">Event curation changes database knowledge only. It does not move, rename, replace or expose originals, derivatives or quarantine objects.</aside>
    </main>
</x-layouts::app>
