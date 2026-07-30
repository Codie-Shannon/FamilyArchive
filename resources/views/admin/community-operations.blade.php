<x-layouts::app title="Community Operations">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Screenshot Group 09 · Post-v1 D</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Real-time family community</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">Operational review for membership, moderated voice and future call infrastructure.</p>
            </div>
            <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                'Community spaces' => $spaces,
                'Active memberships' => $memberships,
                'Voice awaiting review' => $pendingVoiceMessages,
                'Active calls' => $activeCalls,
            ] as $label => $count)
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold text-white">{{ $count }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">Deployment boundary</p>
                        <h2 class="mt-1 text-xl font-semibold text-white">Voice-call readiness</h2>
                    </div>
                    <span class="rounded-full bg-amber-950 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-300">Setup required</span>
                </div>
                <div class="mt-5 space-y-3">
                    @foreach([
                        'Calls explicitly enabled' => $readiness['calls_enabled'],
                        'Signalling endpoint configured' => $readiness['signalling_ready'],
                        'TURN relay configured' => $readiness['turn_ready'],
                    ] as $label => $ready)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <span class="text-zinc-300">{{ $label }}</span>
                            <span class="{{ $ready ? 'text-emerald-300' : 'text-amber-300' }}">{{ $ready ? 'Ready' : 'Not configured' }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-5 rounded-lg border border-amber-900 bg-amber-950/20 p-4 text-sm leading-6 text-amber-100">
                    Live calls remain unavailable until signalling, TURN and browser interoperability tests all pass.
                </p>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <h2 class="text-xl font-semibold text-white">Recent call records</h2>
                <p class="mt-1 text-sm text-zinc-400">Historical state only. A record never proves live infrastructure readiness.</p>
                <div class="mt-5 space-y-3">
                    @forelse($recentCalls as $call)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div>
                                <p class="font-semibold text-white">{{ $call->space_name }} · {{ $call->channel_name }}</p>
                                <p class="mt-1 text-sm text-zinc-500">Started by {{ $call->started_by_name }}</p>
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-wide {{ $call->state === 'ended' ? 'text-zinc-400' : 'text-amber-300' }}">{{ $call->state }}</span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-5 text-zinc-400">No call records.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <aside class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 text-zinc-300">
            Storage keys, message checksums, call identifiers, diagnostics and infrastructure secrets are excluded from this operations view.
        </aside>
    </main>
</x-layouts::app>
