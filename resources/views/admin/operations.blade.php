<x-layouts::app title="Integrity Operations">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Integrity and recovery</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Integrity and production operations</h1>
                <p class="mt-2 text-zinc-400">Observe verified transfers, integrity findings, repair review and recovery readiness.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Integrity observations</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($checkTotal) }}</p>
                <p class="mt-1 text-sm text-emerald-300">{{ number_format($checks['verified'] ?? 0) }} verified</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Review required</p>
                <p class="mt-2 text-3xl font-semibold text-amber-300">{{ number_format($mismatchTotal) }}</p>
                <p class="mt-1 text-sm text-zinc-400">Mismatch or provider finding</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Open repair cases</p>
                <p class="mt-2 text-3xl font-semibold text-amber-300">{{ number_format($repairs->count()) }}</p>
                <p class="mt-1 text-sm text-zinc-400">Human decision required</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Transfer boundary</p>
                <p class="mt-2 text-xl font-semibold text-white">No overwrite</p>
                <p class="mt-1 text-sm text-emerald-300">{{ number_format($transfers['verified'] ?? 0) }} destination checks passed</p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Verification and repair state</h2>
                        <p class="mt-1 text-sm text-zinc-400">Findings create review cases; stored objects are not changed.</p>
                    </div>
                    <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-200">
                        Human reviewed
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach(['verified', 'hash_mismatch', 'size_mismatch', 'missing', 'provider_error'] as $result)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-3">
                            <p class="text-xs uppercase tracking-wide text-zinc-500">{{ str($result)->headline() }}</p>
                            <p class="mt-1 text-xl font-semibold {{ $result === 'verified' ? 'text-emerald-300' : 'text-amber-300' }}">
                                {{ number_format($checks[$result] ?? 0) }}
                            </p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-3">
                    @forelse($repairs as $repair)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-900/70 bg-amber-950/20 p-4">
                            <div>
                                <p class="font-semibold text-white">{{ str($repair->result)->headline() }}</p>
                                <p class="mt-1 text-sm text-zinc-400">Fictional archive object · original retained</p>
                            </div>
                            <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-200">
                                {{ str($repair->state)->headline() }}
                            </span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No open repair cases.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Backup and recovery readiness</h2>
                <p class="mt-1 text-sm text-zinc-400">Synthetic rehearsal records only; not production restore proof.</p>
                <div class="mt-4 space-y-3">
                    @forelse($backups as $backup)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ $backup->backup_set }}</p>
                                <span class="rounded-full px-3 py-1 text-xs uppercase tracking-wide {{ $backup->result === 'verified' ? 'bg-emerald-950 text-emerald-200' : 'bg-amber-950 text-amber-200' }}">
                                    {{ str($backup->result)->headline() }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-400">
                                Isolated rehearsal
                                @if($backup->recovery_point_minutes !== null && $backup->recovery_time_minutes !== null)
                                    · synthetic RPO {{ $backup->recovery_point_minutes }}m · RTO {{ $backup->recovery_time_minutes }}m
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No external backup provider configured.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <h2 class="text-xl font-semibold text-white">Operational events</h2>
            <p class="mt-1 text-sm text-zinc-400">Safe summaries omit providers, endpoints, paths, accounts and real capacity.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse($events as $event)
                    <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-semibold text-white">{{ str($event->type)->headline() }}</p>
                            <span class="text-xs uppercase tracking-wide {{ $event->severity === 'critical' ? 'text-rose-300' : ($event->severity === 'warning' ? 'text-amber-300' : 'text-sky-300') }}">
                                {{ $event->severity }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-zinc-400">{{ $event->safe_summary }}</p>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No unresolved fictional incidents.</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            Verification records failures but never repairs, overwrites, cuts over or claims production hosting automatically.
        </aside>
    </main>
</x-layouts::app>
