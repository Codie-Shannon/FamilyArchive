<x-layouts::app title="Family Archive v1.0">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 05 · Build Groups 45–46</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Family Archive v1.0 acceptance</h1>
                <p class="mt-2 text-zinc-400">Pilot, accessibility, operational acceptance and long-term custodianship.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <nav aria-label="Acceptance sections" class="flex flex-wrap gap-3">
            <a href="#acceptance-matrix" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review acceptance matrix
            </a>
            <a href="#human-gates" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review human gates
            </a>
            <a href="#custodianship-readiness" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review custodianship
            </a>
        </nav>

        <section id="acceptance-matrix" aria-labelledby="acceptance-matrix-heading" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="acceptance-matrix-heading" class="text-xl font-semibold text-white">Deterministic acceptance matrix</h2>
                    <p class="mt-1 text-sm text-zinc-400">Automated checks can identify readiness; they cannot grant human acceptance.</p>
                </div>
                <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-200">
                    {{ $latestRun?->state ? str($latestRun->state)->headline() : 'Not recorded' }}
                </span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($gates as $name => $passed)
                    <article class="flex items-center justify-between gap-4 rounded-lg border {{ $passed ? 'border-emerald-900 bg-emerald-950/20' : 'border-amber-900 bg-amber-950/20' }} p-4">
                        <span class="text-sm text-white">{{ str($name)->replace('_', ' ')->title() }}</span>
                        <strong class="text-xs uppercase tracking-wide {{ $passed ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $passed ? 'Ready' : 'Required' }}
                        </strong>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="human-gates" aria-labelledby="human-gates-heading" class="rounded-xl border border-amber-800 bg-amber-950/15 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="human-gates-heading" class="text-xl font-semibold text-white">Honest human gates</h2>
                    <p class="mt-1 text-sm text-zinc-300">Version metadata is v1.0.0; final acceptance remains human work.</p>
                </div>
                <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-200">Acceptance required</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                @foreach($humanGates as $name => $passed)
                    <article class="rounded-lg border border-amber-900/70 bg-zinc-950 p-4">
                        <p class="font-semibold text-white">{{ $name }}</p>
                        <p class="mt-2 text-sm {{ $passed ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $passed ? 'Recorded by a human' : 'Not recorded — human action required' }}
                        </p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Pilot and accessibility feedback</h2>
                <p class="mt-1 text-sm text-zinc-400">Participant identities and private family details are never displayed.</p>
                <div class="mt-4 space-y-3">
                    @forelse($feedback as $item)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <p class="font-semibold text-white">{{ str($item->area)->headline() }}</p>
                            <span class="text-xs uppercase tracking-wide text-amber-300">
                                {{ $item->severity }} · {{ $item->state }}
                            </span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">A real family pilot has not been claimed or recorded.</p>
                    @endforelse
                </div>
            </article>

            <article id="custodianship-readiness" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Custodianship readiness</h2>
                <p class="mt-1 text-sm text-zinc-400">Designation records responsibility; normal access controls still apply.</p>
                <div class="mt-4 space-y-3">
                    @forelse($custodians as $item)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <p class="font-semibold text-white">{{ str($item->role)->headline() }} custodian</p>
                            <span class="text-xs uppercase tracking-wide {{ $item->state === 'confirmed' ? 'text-emerald-300' : 'text-amber-300' }}">
                                {{ $item->state }}
                            </span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">Custodians must be nominated and confirm their responsibility manually.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section aria-labelledby="walkthrough-heading" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <h2 id="walkthrough-heading" class="text-xl font-semibold text-white">Whole-system walkthrough</h2>
            <p class="mt-1 text-sm text-zinc-400">Curated v1.0 capability overview inside the verified Owner boundary.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach([
                    'Preserve immutable originals',
                    'Review duplicate and restoration candidates',
                    'Curate provenance-aware archive knowledge',
                    'Control family access and conversation',
                    'Verify integrity, backups and repair cases',
                    'Plan long-term custodianship',
                ] as $capability)
                    <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4 text-sm text-zinc-200">{{ $capability }}</div>
                @endforeach
            </div>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            v1.0.0 is the release candidate metadata. Repository automation does not fabricate pilot approval, production proof or custodian confirmation.
        </aside>
    </main>
</x-layouts::app>
