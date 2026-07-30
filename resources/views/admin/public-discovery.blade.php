<x-layouts::app title="Public Discovery Review">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 08 · Post-v1 C</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Public discovery review</h1>
                <p class="mt-2 text-zinc-400">Approve showcase stories, social cards and privacy-safe map points before publication.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                'Published' => $entries->where('state', 'published')->count(),
                'In review' => $entries->where('state', 'review')->count(),
                'Draft' => $entries->where('state', 'draft')->count(),
                'Withdrawn' => $entries->where('state', 'withdrawn')->count(),
            ] as $label => $count)
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold text-white">{{ $count }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.25fr_0.75fr]">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Showcase publication queue</h2>
                <p class="mt-1 text-sm text-zinc-400">Only the published state is visible outside the Owner boundary.</p>
                <div class="mt-4 space-y-3">
                    @forelse($entries as $entry)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ $entry->public_title }}</p>
                                <span class="text-xs uppercase tracking-wide {{ $entry->state === 'published' ? 'text-emerald-300' : 'text-amber-300' }}">{{ $entry->state }}</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-400">{{ $entry->public_summary }}</p>
                            <p class="mt-3 text-xs text-zinc-600">{{ $entry->allow_social_cards ? 'Social card permitted after review' : 'Social publication disabled' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No public showcase drafts.</p>
                    @endforelse
                </div>
            </article>

            <div class="space-y-5">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <h2 class="text-xl font-semibold text-white">Map privacy review</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($points as $point)
                            <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-white">{{ $point->public_place_name }}</p>
                                    <span class="text-xs uppercase tracking-wide {{ $point->privacy_reviewed && $point->precision !== 'exact' ? 'text-emerald-300' : 'text-amber-300' }}">
                                        {{ $point->privacy_reviewed && $point->precision !== 'exact' ? 'Approved' : 'Blocked' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-zinc-400">{{ $point->public_title }} · {{ $point->precision }} precision</p>
                            </div>
                        @empty
                            <p class="text-zinc-400">No map points awaiting review.</p>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <h2 class="text-xl font-semibold text-white">Publication receipts</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($receipts as $receipt)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                                <div>
                                    <p class="font-semibold text-white">{{ str($receipt->channel)->headline() }}</p>
                                    <p class="mt-1 text-sm text-zinc-500">{{ $receipt->public_title }}</p>
                                </div>
                                <span class="text-xs uppercase tracking-wide text-emerald-300">{{ $receipt->state }}</span>
                            </div>
                        @empty
                            <p class="text-zinc-400">No social publication receipts.</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            Exact private coordinates, unreviewed stories and private archive records cannot cross this publication boundary.
        </aside>
    </main>
</x-layouts::app>
