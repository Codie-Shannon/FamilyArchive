<x-layouts::app title="Reviewed Person">
    <x-archive-shell>
        @if(session('status'))<div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-mono text-xs text-zinc-500">{{ $person->person_id }}</p>
                <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">{{ $personPresenter->browseName($person) }}</h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $personPresenter->lifeDates($person) }}</p>
            </div>
            @if($canCurate)<a href="{{ route('archive.people.edit', $person) }}" class="rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Review person</a>@endif
        </header>

        @if($person->is_private)
            <aside class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"><strong>Sensitive person boundary</strong><p class="mt-1 text-sm">Names, life dates, notes, branch membership and provenance details are withheld from this browse surface. The Owner may review them through the controlled revision form.</p></aside>
        @endif

        <section class="grid gap-4 md:grid-cols-4">
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Name certainty</p><p class="mt-2 font-semibold">{{ $person->is_private ? 'Withheld' : str($person->name_certainty->value)->headline() }}</p></article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Alternate names</p><p class="mt-2 text-sm font-semibold">{{ $personPresenter->alternateNames($person) }}</p></article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Family branch</p>@if(!$person->is_private && $person->familyBranch)<a class="mt-2 block font-semibold" href="{{ route('archive.branches.show', $person->familyBranch) }}">{{ $branchPresenter->browseName($person->familyBranch) }}</a><p class="font-mono text-xs text-zinc-500">{{ $person->familyBranch->branch_id }}</p>@else<p class="mt-2 text-sm font-semibold">{{ $person->is_private ? 'Withheld' : 'No reviewed branch' }}</p>@endif</article>
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700"><p class="text-xs uppercase text-zinc-500">Reviewed status</p><p class="mt-2 font-semibold">Accepted</p><p class="text-xs text-zinc-500">{{ str($person->fact_confidence->value)->headline() }} confidence</p></article>
        </section>

        @if(!$person->is_private)
            <section class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><h2 class="text-xl font-semibold">Reviewed evidence note</h2><p class="mt-3 whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $person->source_note ?: 'No reviewed source note.' }}</p></article>
                <article class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><h2 class="text-xl font-semibold">Reviewed context</h2><p class="mt-3 whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $person->notes ?: 'No reviewed context.' }}</p></article>
            </section>
        @endif

        @if($canCurate)
        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">Source provenance</h2><span class="text-xs text-zinc-500">{{ $person->provenanceLinks->count() }} reviewed links</span></div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse($person->provenanceLinks as $link)
                    <a href="{{ route('archive.sources.show', $link->sourceCollection) }}" class="block rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <strong>{{ $person->is_private ? 'Reviewed source withheld' : $link->sourceCollection->name }}</strong>
                        @if(!$person->is_private)<p class="font-mono text-xs text-zinc-500">{{ $link->sourceCollection->source_id }}@if($link->scanBatch) · {{ $link->scanBatch->scan_batch_id }}@endif</p><p class="mt-1 text-sm text-zinc-500">{{ $link->note }}</p>@endif
                    </a>
                @empty
                    <p class="text-zinc-500 md:col-span-2">No reviewed source is linked yet.</p>
                @endforelse
            </div>
            @if($sources->isNotEmpty())
                <form method="POST" action="{{ route('archive.people.provenance.store', $person) }}" class="mt-5 grid gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="expected_metadata_revision" value="{{ $person->metadata_revision }}">
                    <select name="source_collection_id" required class="rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="">Select source</option>@foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->source_id }} · {{ $source->name }}</option>@endforeach</select>
                    <select name="scan_batch_id" class="rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="">No scan batch</option>@foreach($sources as $source)@foreach($source->scanBatches as $batch)<option value="{{ $batch->id }}">{{ $source->source_id }} · {{ $batch->label }}</option>@endforeach @endforeach</select>
                    <textarea name="note" rows="2" placeholder="Reviewed provenance note" class="rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                    <textarea name="change_reason" required rows="2" placeholder="Reason for attaching this source" class="rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                    <button class="w-fit rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-black">Attach source</button>
                </form>
            @endif
        </section>

        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <h2 class="text-xl font-semibold">Immutable revision evidence</h2>
            <div class="mt-4 grid gap-5 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Person record</h3>
                    <div class="mt-3 space-y-3">@foreach($person->revisions as $revision)<article class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-900"><strong>Revision {{ $revision->revision_number }}</strong><p class="text-zinc-500">{{ implode(', ', $revision->changed_fields) }} · {{ $revision->actor->name }}</p><p class="mt-1">{{ $revision->change_reason }}</p></article>@endforeach</div>
                </div>
                @if(!$person->is_private && $person->familyBranch)
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Family branch</h3>
                        <div class="mt-3 space-y-3">@foreach($person->familyBranch->revisions as $revision)<article class="rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-900"><strong>Revision {{ $revision->revision_number }}</strong><p class="text-zinc-500">{{ implode(', ', $revision->changed_fields) }} · {{ $revision->actor->name }}</p><p class="mt-1">{{ $revision->change_reason }}</p></article>@endforeach</div>
                    </div>
                @endif
            </div>
        </section>

        @else
            <aside class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-5 text-sm text-emerald-100">You are viewing accepted family knowledge. Evidence attachments and immutable revision history remain in the controlled curation workspace.</aside>
        @endif
    </x-archive-shell>
</x-layouts::app>
