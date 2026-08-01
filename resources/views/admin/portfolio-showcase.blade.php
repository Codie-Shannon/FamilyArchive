<x-layouts::app title="Portfolio Showcase">
    <main class="mx-auto max-w-7xl space-y-6 p-6">
        <header class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Product overview</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Preservation engineering, made demonstrable</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">A privacy-first, preservation-grade platform for protecting, understanding and selectively sharing family history.</p>
            </div>
            <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <nav aria-label="Portfolio evidence views" class="grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
            @foreach([
                'promise' => 'Product promise',
                'journey' => 'Core journey',
                'integrity' => 'Integrity proof',
                'privacy' => 'Privacy proof',
                'architecture' => 'Architecture',
                'accessibility' => 'Responsive access',
            ] as $key => $label)
                <a
                    href="{{ route('admin.portfolio-showcase', ['view' => $key]) }}"
                    class="rounded-lg border px-3 py-2 text-center text-sm font-medium {{ $activeView === $key ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200' : 'border-zinc-700 bg-zinc-900 text-zinc-400' }}"
                    @if($activeView === $key) aria-current="page" @endif
                >{{ $label }}</a>
            @endforeach
        </nav>

        @if($activeView === 'promise')
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach($metrics as $label => $value)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ str($label)->replace('_', ' ') }}</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                <article class="rounded-xl border border-emerald-900 bg-zinc-900 p-6">
                    <p class="text-sm font-semibold text-emerald-300">Core product</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">Protect the evidence. Preserve the story.</h2>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach($positioning['core'] as $item)
                            <div class="flex gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-4 text-zinc-300">
                                <span aria-hidden="true" class="text-emerald-300">◆</span>
                                <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <div class="space-y-5">
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <p class="text-sm font-semibold text-sky-300">Supporting experience</p>
                        <ul class="mt-3 space-y-3 text-zinc-300">
                            @foreach($positioning['supporting'] as $item)
                                <li>• {{ $item }}</li>
                            @endforeach
                        </ul>
                    </article>
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <p class="text-sm font-semibold text-zinc-400">De-emphasized expansion</p>
                        <p class="mt-3 text-sm leading-6 text-zinc-500">Generic social-network growth is not the product promise. Deferred capabilities remain bounded and fail closed.</p>
                    </article>
                </div>
            </section>
        @elseif($activeView === 'journey')
            <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">One coherent archive workflow</p>
                        <h2 class="mt-1 text-2xl font-semibold text-white">Ingest → verify → review → enrich → preserve → share</h2>
                    </div>
                    <span class="rounded-full bg-emerald-950 px-4 py-2 text-sm text-emerald-200">Human decisions stay visible</span>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach([
                        ['01', 'Ingest', 'Private quarantine', 'Validate type, size and identity before retention.'],
                        ['02', 'Verify', 'Integrity boundary', 'Record SHA-256, bytes and immutable source identity.'],
                        ['03', 'Review', 'Human gate', 'Resolve duplicates and accept originals deliberately.'],
                        ['04', 'Enrich', 'Provenance', 'Add uncertain dates, sources and revisions without invented precision.'],
                        ['05', 'Preserve', 'Lineage and recovery', 'Keep originals immutable; rebuild derivatives and verify backups.'],
                        ['06', 'Share', 'Controlled access', 'Use private roles or explicitly reviewed public stories and reduced map precision.'],
                    ] as [$number, $title, $boundary, $description])
                        <article class="rounded-xl border border-zinc-700 bg-zinc-950 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-2xl font-semibold text-emerald-400">{{ $number }}</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $boundary }}</span>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold text-white">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-zinc-400">{{ $description }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
            <aside class="rounded-xl border border-amber-900 bg-amber-950/20 p-5 text-amber-100">
                No automated step can delete a suspected duplicate, replace an accepted original, publish private knowledge or manufacture historical certainty.
            </aside>
        @elseif($activeView === 'integrity')
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($integrityProof as $label => $value)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ str($label)->replace('_', ' ') }}</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
                <article class="rounded-xl border border-emerald-900 bg-zinc-900 p-6">
                    <p class="text-sm font-semibold text-emerald-300">Immutable lineage</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">Originals remain the source of truth</h2>
                    <div class="mt-6 flex flex-wrap items-stretch gap-3">
                        @foreach([
                            ['Quarantine source', 'Signature validated'],
                            ['Accepted original', 'SHA-256 verified'],
                            ['Web display', 'Rebuildable derivative'],
                            ['Thumbnail', 'Parent lineage recorded'],
                        ] as [$title, $state])
                            <div class="min-w-44 flex-1 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                                <p class="font-semibold text-white">{{ $title }}</p>
                                <p class="mt-2 text-sm text-emerald-300">{{ $state }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-5 text-sm text-zinc-500">Hashes, storage coordinates and object identifiers remain private; this evidence displays only safe verification state.</p>
                </article>

                <div class="space-y-4">
                    @foreach([
                        ['No-overwrite transfer', 'Existing destinations are refused before any write.'],
                        ['Append-only checking', 'Integrity observations never mutate the stored object.'],
                        ['Recovery evidence', 'Backup verification uses isolated synthetic restore namespaces.'],
                    ] as [$title, $description])
                        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-white">{{ $title }}</h3>
                                <span class="text-emerald-300">Verified boundary</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-zinc-400">{{ $description }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @elseif($activeView === 'privacy')
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($privacyProof as $label => $value)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ str($label)->replace('_', ' ') }}</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-5 lg:grid-cols-3">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                    <p class="text-sm font-semibold text-emerald-300">Role boundary</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Private archive</h2>
                    <ul class="mt-5 space-y-3 text-zinc-300">
                        <li>◆ Verified Owner administration</li>
                        <li>◆ Approved account state</li>
                        <li>◆ Revocable original-access grants</li>
                        <li>◆ Sensitive records withheld by default</li>
                    </ul>
                </article>
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                    <p class="text-sm font-semibold text-sky-300">Publication boundary</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Human review gates</h2>
                    <ul class="mt-5 space-y-3 text-zinc-300">
                        <li>◆ Draft and review records stay private</li>
                        <li>◆ Only approved stories reach public output</li>
                        <li>◆ External receipts stay inside Owner views</li>
                        <li>◆ Private archive access never follows a public link</li>
                    </ul>
                </article>
                <article class="rounded-xl border border-emerald-900 bg-zinc-900 p-6">
                    <p class="text-sm font-semibold text-emerald-300">Location boundary</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Reduced precision only</h2>
                    <div class="mt-5 rounded-lg border border-zinc-700 bg-zinc-950 p-5">
                        <p class="font-semibold text-white">Fictional Wellington Region</p>
                        <p class="mt-2 text-sm text-zinc-400">Public label: region</p>
                        <p class="mt-1 text-sm text-emerald-300">Privacy reviewed</p>
                    </div>
                    <p class="mt-4 text-sm text-zinc-500">Exact private coordinates are removed before the public read model renders.</p>
                </article>
            </section>
            <aside class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 text-zinc-300">
                Fictional dataset · no real identities · no private paths · no precise private locations · no credentials.
            </aside>
        @elseif($activeView === 'architecture')
            <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">Modular Laravel monolith</p>
                        <h2 class="mt-1 text-2xl font-semibold text-white">System boundaries and storage flow</h2>
                    </div>
                    <span class="text-sm text-zinc-500">Private by default · review before consequence</span>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-[0.8fr_1.4fr_0.8fr]">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Interfaces</p>
                        @foreach(['Owner administration', 'Family workspace', 'Restricted public discovery'] as $label)
                            <div class="rounded-lg border border-sky-900 bg-sky-950/20 p-4 text-sky-100">{{ $label }}</div>
                        @endforeach
                    </div>

                    <div class="rounded-xl border border-emerald-900 bg-emerald-950/10 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-400">Application boundary</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach([
                                'Intake & quarantine',
                                'Duplicate review',
                                'Archive & derivatives',
                                'Metadata & provenance',
                                'Knowledge & access',
                                'Restoration & intelligence',
                                'Integrity & recovery',
                                'Controlled publication',
                            ] as $module)
                                <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4 text-zinc-200">{{ $module }}</div>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Private storage zones</p>
                        @foreach([
                            ['Quarantine', 'Untrusted retained input'],
                            ['Originals', 'Immutable accepted source'],
                            ['Derivatives', 'Rebuildable output'],
                            ['Manifests', 'Integrity evidence'],
                        ] as [$zone, $description])
                            <div class="rounded-lg border border-amber-900 bg-amber-950/20 p-4">
                                <p class="font-semibold text-amber-100">{{ $zone }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $description }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-4">
                    @foreach(['Authenticate & authorize', 'Validate & retain', 'Human review', 'Append audit evidence'] as $index => $label)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4 text-center text-sm text-zinc-300">
                            <span class="text-emerald-300">{{ $index + 1 }}</span> · {{ $label }}
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <section class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                    <p class="text-sm font-semibold text-emerald-300">Responsive evidence</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">The same protected workflow at every breakpoint</h2>
                    <div class="mt-6 flex items-end justify-center gap-6 rounded-xl border border-zinc-700 bg-zinc-950 p-6">
                        <div class="w-3/5 rounded-lg border-4 border-zinc-700 bg-zinc-900 p-4">
                            <div class="flex gap-3">
                                <div class="w-1/4 space-y-2">
                                    @foreach(range(1, 5) as $item)
                                        <div class="h-3 rounded bg-zinc-700"></div>
                                    @endforeach
                                </div>
                                <div class="flex-1">
                                    <div class="h-4 w-2/3 rounded bg-emerald-800"></div>
                                    <div class="mt-4 grid grid-cols-3 gap-2">
                                        @foreach(range(1, 6) as $item)
                                            <div class="h-16 rounded bg-zinc-800"></div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <p class="mt-4 text-center text-xs text-zinc-500">Desktop · persistent navigation · multi-column evidence</p>
                        </div>
                        <div class="w-1/4 rounded-[1.5rem] border-4 border-zinc-700 bg-zinc-900 p-3">
                            <div class="mx-auto h-2 w-12 rounded bg-zinc-700"></div>
                            <div class="mt-4 h-4 w-3/4 rounded bg-emerald-800"></div>
                            <div class="mt-4 space-y-2">
                                @foreach(range(1, 4) as $item)
                                    <div class="h-12 rounded bg-zinc-800"></div>
                                @endforeach
                            </div>
                            <p class="mt-4 text-center text-xs text-zinc-500">Mobile · stacked cards · stashable navigation</p>
                        </div>
                    </div>
                </article>

                <div class="space-y-4">
                    @foreach([
                        ['Keyboard path', 'Logical navigation order with visible focus states.'],
                        ['Semantic structure', 'Named navigation, main content, headings and status text.'],
                        ['Responsive layout', 'Desktop grids collapse into readable stacked content.'],
                        ['Non-colour cues', 'Every status includes readable text, not colour alone.'],
                        ['Motion boundary', 'No required animation or time-dependent interaction.'],
                    ] as [$title, $description])
                        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                            <div class="flex items-start gap-3">
                                <span class="text-emerald-300" aria-hidden="true">✓</span>
                                <div>
                                    <h3 class="font-semibold text-white">{{ $title }}</h3>
                                    <p class="mt-1 text-sm text-zinc-400">{{ $description }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            <aside class="rounded-xl border border-emerald-900 bg-emerald-950/20 p-5 text-emerald-100">
                Read-only fictional demonstration · authentication preserved · controls remain reachable without pointer input.
            </aside>
        @endif

        <footer class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-700 bg-zinc-900 px-5 py-4 text-sm">
            <span class="text-zinc-300">Fictional Aotearoa dataset · no real family data</span>
            <span class="{{ $safeguards['enabled'] ? 'text-emerald-300' : 'text-amber-300' }}">
                {{ $safeguards['enabled'] ? 'Read-only demo mode enabled' : 'Read-only demo mode available; runtime setup required' }}
            </span>
        </footer>
    </main>
</x-layouts::app>
