<x-layouts::app title="Production Readiness">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 15 · Hosted Production</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Production readiness</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">
                    Verify the live HTTPS boundary, durable application state, private archive storage and hardened responses without exposing infrastructure identifiers.
                </p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-xl border {{ $report['ready'] ? 'border-emerald-800 bg-emerald-950/20' : 'border-amber-800 bg-amber-950/20' }} p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-400">Deployment proof</p>
                <p class="mt-2 text-2xl font-semibold {{ $report['ready'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $report['ready'] ? 'Verified' : 'Pending' }}
                </p>
                <p class="mt-1 text-sm text-zinc-400">Recorded only after a live production probe.</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Transport</p>
                <p class="mt-2 text-2xl font-semibold text-white">HTTPS + HSTS</p>
                <p class="mt-1 text-sm text-zinc-400">Certificate validation and hardened response headers.</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Application state</p>
                <p class="mt-2 text-2xl font-semibold text-white">Durable</p>
                <p class="mt-1 text-sm text-zinc-400">Database-backed cache, sessions and queued work.</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Archive boundary</p>
                <p class="mt-2 text-2xl font-semibold text-white">Private Wasabi</p>
                <p class="mt-1 text-sm text-zinc-400">Versioned no-overwrite storage verified separately.</p>
            </article>
        </section>

        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Live production gates</h2>
                    <p class="mt-1 text-sm text-zinc-400">Every gate must pass in the deployed environment.</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs uppercase tracking-wide {{ $report['ready'] ? 'bg-emerald-950 text-emerald-200' : 'bg-amber-950 text-amber-200' }}">
                    {{ $report['ready'] ? 'All gates passed' : 'Live verification required' }}
                </span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($report['gates'] as $name => $passed)
                    <article class="flex items-center justify-between gap-4 rounded-lg border {{ $passed ? 'border-emerald-900/80 bg-emerald-950/15' : 'border-amber-900/80 bg-amber-950/15' }} p-4">
                        <p class="text-sm font-medium text-white">{{ $labels[$name] ?? str($name)->replace('_', ' ')->title() }}</p>
                        <strong class="text-xs uppercase tracking-wide {{ $passed ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $passed ? 'Ready' : 'Required' }}
                        </strong>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Security boundary</h2>
                <ul class="mt-4 space-y-3 text-sm text-zinc-300">
                    <li class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">Debug output is disabled before production verification can pass.</li>
                    <li class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">Authenticated responses are private and excluded from browser or intermediary caches.</li>
                    <li class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">Framing, object embedding, unsafe referrers and unnecessary browser capabilities are restricted.</li>
                    <li class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">The health endpoint checks both the database and shared cache.</li>
                </ul>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Latest deployment evidence</h2>
                @if($report['latest'] !== null)
                    <div class="mt-4 rounded-lg border {{ $report['latest']->resolved_at ? 'border-emerald-900 bg-emerald-950/15' : 'border-rose-900 bg-rose-950/15' }} p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-semibold text-white">{{ $report['latest']->resolved_at ? 'Live verification passed' : 'Verification failed closed' }}</p>
                            <span class="text-xs uppercase tracking-wide {{ $report['latest']->resolved_at ? 'text-emerald-300' : 'text-rose-300' }}">
                                {{ $report['latest']->resolved_at ? 'Resolved' : 'Review required' }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-zinc-400">{{ $report['latest']->safe_summary }}</p>
                    </div>
                @else
                    <p class="mt-4 rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">
                        No live deployment verification has been recorded.
                    </p>
                @endif

                <p class="mt-4 text-sm text-zinc-400">
                    Evidence omits the hostname, IP address, database endpoint, provider account, bucket, object key, storage path and every credential.
                </p>
            </article>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            A successful build is not deployment proof. This page reports verified only after the running HTTPS application completes its database, cache, response-header and private-storage checks.
        </aside>
    </main>
</x-layouts::app>
