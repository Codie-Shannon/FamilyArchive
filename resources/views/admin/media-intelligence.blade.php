<x-layouts::app title="Advanced Media Intelligence">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 06 · Post-v1 A</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Advanced media intelligence</h1>
                <p class="mt-2 text-zinc-400">Visual similarity, alternate originals and provenance-aware merge previews.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <nav aria-label="Media intelligence sections" class="flex flex-wrap gap-3">
            <a href="#similarity-review" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review similarity candidates
            </a>
            <a href="#merge-review" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review metadata conflicts
            </a>
        </nav>

        <section class="grid gap-5 xl:grid-cols-2">
            <article id="similarity-review" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Similarity review</h2>
                        <p class="mt-1 text-sm text-zinc-400">Candidates stay pending until a human records a relationship decision.</p>
                    </div>
                    <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-200">
                        {{ $candidates->where('review_state', 'pending')->count() }} pending
                    </span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse($candidates as $item)
                        <div class="rounded-lg border border-amber-900/70 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ str($item->method)->headline() }} candidate</p>
                                <span class="text-xs uppercase tracking-wide text-amber-300">{{ str($item->review_state)->headline() }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <p class="rounded border border-zinc-800 p-3 text-zinc-300">Distance <strong class="block text-lg text-white">{{ $item->distance }}</strong></p>
                                <p class="rounded border border-zinc-800 p-3 text-zinc-300">Confidence <strong class="block text-lg text-white">{{ number_format((float) $item->confidence * 100, 1) }}%</strong></p>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No visual candidates awaiting review.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Alternate original tracking</h2>
                <p class="mt-1 text-sm text-zinc-400">Alternate sources retain their own file and provenance identity.</p>
                <div class="mt-4 space-y-3">
                    @forelse($alternates as $item)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div>
                                <p class="font-semibold text-white">Reviewed source record</p>
                                <p class="mt-1 text-sm text-zinc-400">The preserved file remains separate and immutable.</p>
                            </div>
                            <span class="text-xs uppercase tracking-wide {{ $item->is_preferred_source ? 'text-emerald-300' : 'text-sky-300' }}">
                                {{ $item->is_preferred_source ? 'Preferred' : 'Alternate' }}
                            </span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No alternate sources recorded.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section id="merge-review" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Conflict-aware metadata merge review</h2>
                    <p class="mt-1 text-sm text-zinc-400">Blank target fields may be proposed; conflicting reviewed facts require an explicit decision.</p>
                </div>
                <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-200">Human review required</span>
            </div>
            <div class="mt-4 space-y-4">
                @forelse($merges as $item)
                    @php
                        $decisions = json_decode($item->field_decisions, true, flags: JSON_THROW_ON_ERROR);
                        $conflicts = json_decode($item->conflicts, true, flags: JSON_THROW_ON_ERROR);
                    @endphp
                    <article class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-semibold text-white">Metadata proposal</p>
                            <span class="text-xs uppercase tracking-wide text-amber-300">{{ str($item->state)->headline() }}</span>
                        </div>
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-300">Safe additions</h3>
                                @forelse($decisions as $field => $value)
                                    <p class="mt-2 rounded border border-emerald-900/70 bg-emerald-950/20 p-3 text-sm text-zinc-200">
                                        <strong>{{ str($field)->headline() }}:</strong> {{ $value }}
                                    </p>
                                @empty
                                    <p class="mt-2 text-sm text-zinc-500">No automatic additions proposed.</p>
                                @endforelse
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-amber-300">Conflicts</h3>
                                @forelse($conflicts as $field => $values)
                                    <div class="mt-2 rounded border border-amber-900/70 bg-amber-950/20 p-3 text-sm text-zinc-200">
                                        <p class="font-semibold text-white">{{ str($field)->headline() }}</p>
                                        <p class="mt-2">Keep reviewed: {{ $values['target'] }}</p>
                                        <p class="mt-1">Proposed source: {{ $values['source'] }}</p>
                                    </div>
                                @empty
                                    <p class="mt-2 text-sm text-zinc-500">No conflicting reviewed facts.</p>
                                @endforelse
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No merge proposals awaiting review.</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            Similarity is candidate-only. No match deletes media, changes the preferred original or merges conflicting facts without human approval.
        </aside>
    </main>
</x-layouts::app>
