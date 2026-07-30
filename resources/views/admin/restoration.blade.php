<x-layouts::app title="Restoration Workspace">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 03 · Build Groups 29–36</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Collaboration and restoration</h1>
                <p class="mt-2 text-zinc-400">Versioned recipes produce review candidates. Originals remain immutable.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Active provider</p>
                <h2 class="mt-1 text-xl font-semibold text-white">{{ str($provider['provider'])->headline() }}</h2>
                <p class="mt-2 text-emerald-300">Private · configured</p>
                <p class="mt-1 text-sm text-zinc-400">Provider-neutral storage remains behind the archive boundary.</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">External provider boundary</p>
                <h2 class="mt-1 text-xl font-semibold text-white">Wasabi readiness</h2>
                <p class="mt-2 text-xl {{ $wasabi['configured'] ? 'text-emerald-300' : 'text-amber-300' }}">
                    {{ $wasabi['configured'] ? 'Configuration present' : 'External configuration required' }}
                </p>
                <p class="mt-1 text-sm text-zinc-400">No credentials, endpoint or bucket names are rendered or stored in Git. This status does not claim a live connection.</p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Versioned recipes</h2>
                <p class="mt-1 text-sm text-zinc-400">Only approved operations can enter a recipe.</p>
                <div class="mt-4 space-y-3">
                    @forelse($recipes as $recipe)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-white">{{ $recipe->name }}</p>
                                <span class="rounded-full bg-emerald-950 px-3 py-1 text-xs text-emerald-200">Version {{ $recipe->version }}</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-400">
                                Operations: {{ collect(json_decode($recipe->operations, true, flags: JSON_THROW_ON_ERROR))->keys()->map(fn ($key) => str($key)->headline())->join(' · ') }}
                            </p>
                            <p class="mt-1 font-mono text-xs text-zinc-600">{{ $recipe->recipe_id }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No recipes created.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Restoration review queue</h2>
                <p class="mt-1 text-sm text-zinc-400">Queued work creates candidates; it never replaces a preferred original.</p>
                <div class="mt-4 space-y-3">
                    @forelse($jobs as $job)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-white">{{ $job->recipe_name }} · v{{ $job->recipe_version }}</p>
                                <span class="rounded-full bg-sky-950 px-3 py-1 text-xs uppercase tracking-wide text-sky-200">{{ str($job->state)->headline() }}</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-400">Attempts: {{ $job->attempts }} · immutable source retained</p>
                            <p class="mt-1 font-mono text-xs text-zinc-600">{{ $job->job_id }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No restoration candidates awaiting review.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            No recipe, batch or provider automatically changes the preferred version, overwrites an original or claims a live Wasabi connection.
        </aside>
    </main>
</x-layouts::app>
