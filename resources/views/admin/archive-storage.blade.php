<x-layouts::app :title="__('Archive Storage')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Owner administration</p>
                    <h1 class="mt-2 text-3xl font-semibold text-white">Private Archive Storage</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-300">Provider-neutral archive paths backed by local private storage or a production Wasabi boundary with verified, versioned and no-overwrite writes.</p>
                </div>
                <span class="rounded-full border border-emerald-700 bg-emerald-950/50 px-4 py-2 text-sm font-semibold text-emerald-300">{{ $provider['state'] === 'ready' ? 'Wasabi configured' : ($provider['state'] === 'local' ? 'Private local mode' : 'Configuration required') }}</span>
            </div>
        </header>

        <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
            <article class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-6">
                <p class="text-sm font-medium text-emerald-300">Production provider boundary</p>
                <div class="mt-2 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-white">{{ strtoupper($provider['provider']) }}</h2>
                    <span class="rounded-full bg-zinc-950/60 px-3 py-1 text-xs font-semibold text-emerald-300">{{ strtoupper($provider['state']) }}</span>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Configuration</p><p class="mt-2 font-semibold {{ $provider['configured'] ? 'text-emerald-300' : 'text-amber-300' }}">{{ $provider['configured'] ? 'Complete' : 'Incomplete' }}</p></div>
                    <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Visibility</p><p class="mt-2 font-semibold text-emerald-300">{{ $provider['private'] ? 'Private only' : 'Check required' }}</p></div>
                    <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Versioning</p><p class="mt-2 font-semibold {{ $latestProviderVerification?->versioning_enabled ? 'text-emerald-300' : 'text-zinc-300' }}">{{ $latestProviderVerification?->versioning_enabled ? 'Verified' : 'Awaiting live check' }}</p></div>
                    <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Object Lock</p><p class="mt-2 font-semibold {{ $latestProviderVerification?->object_lock_enabled ? 'text-emerald-300' : 'text-zinc-300' }}">{{ $latestProviderVerification?->object_lock_enabled ? 'Capability verified' : 'Awaiting live check' }}</p></div>
                </div>
                <p class="mt-4 text-xs leading-5 text-zinc-500">Credentials, bucket identity, endpoint, object keys and version IDs are deliberately excluded from this screen.</p>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-sm font-medium text-zinc-400">Latest live provider proof</p><h2 class="mt-1 text-xl font-semibold text-white">Write · exact-version readback · cleanup</h2></div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $latestProviderVerification?->state === 'verified' ? 'bg-emerald-950 text-emerald-300' : 'bg-zinc-800 text-zinc-300' }}">{{ strtoupper($latestProviderVerification?->state ?? 'not run') }}</span>
                </div>
                @if ($latestProviderVerification)
                    <p class="mt-5 text-sm leading-6 text-zinc-300">{{ $latestProviderVerification->safe_summary }}</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-zinc-800 p-4"><p class="text-xs text-zinc-400">Bucket access</p><p class="mt-2 font-semibold {{ $latestProviderVerification->bucket_access ? 'text-emerald-300' : 'text-amber-300' }}">{{ $latestProviderVerification->bucket_access ? 'Passed' : 'Failed' }}</p></div>
                        <div class="rounded-lg bg-zinc-800 p-4"><p class="text-xs text-zinc-400">Versioned readback</p><p class="mt-2 font-semibold {{ $latestProviderVerification->write_read_delete_verified ? 'text-emerald-300' : 'text-amber-300' }}">{{ $latestProviderVerification->write_read_delete_verified ? 'Passed' : 'Failed' }}</p></div>
                        <div class="rounded-lg bg-zinc-800 p-4"><p class="text-xs text-zinc-400">Checked</p><p class="mt-2 text-sm font-semibold text-white">{{ $latestProviderVerification->checked_at->format('d M Y H:i') }}</p></div>
                    </div>
                @else
                    <p class="mt-5 text-sm leading-6 text-zinc-300">No live provider verification is recorded yet. The verification command uses a randomized synthetic object under the isolated health prefix and removes only its exact version.</p>
                @endif
            </article>
        </section>

        <section>
            <div class="mb-3"><p class="text-sm font-medium text-zinc-400">Least-privilege object boundaries</p><h2 class="text-xl font-semibold text-white">One private bucket, five isolated prefixes</h2></div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($providerBoundaries as $boundary)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-4"><p class="text-sm font-semibold text-white">{{ $boundary['label'] }}</p><code class="mt-2 block text-xs text-emerald-300">{{ $boundary['prefix'] }}</code><p class="mt-3 text-xs leading-5 text-zinc-400">{{ $boundary['rule'] }}</p></article>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-sky-800 bg-sky-950/20 p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-sky-300">Read-only migration plan</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Copy first · verify remote · retain local</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-300">Planning inspects the four always-local source disks without contacting Wasabi. Execution requires an explicit console flag, refuses mismatched collisions and never removes a local source object.</p>
                </div>
                <span class="w-fit rounded-full border border-sky-800 bg-zinc-950/50 px-4 py-2 text-xs font-semibold text-sky-300">EXECUTION NOT RUN</span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Objects planned</p><p class="mt-2 text-2xl font-semibold text-white">{{ number_format($migrationPlan['planned']) }}</p></div>
                <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Source bytes</p><p class="mt-2 text-2xl font-semibold text-white">{{ number_format($migrationPlan['bytes']) }}</p></div>
                <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Remote writes</p><p class="mt-2 text-2xl font-semibold text-sky-300">0</p></div>
                <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400">Local deletion</p><p class="mt-2 text-lg font-semibold text-emerald-300">Unavailable</p></div>
            </div>
        </section>

        <section>
            <div class="mb-3 flex items-end justify-between gap-4">
                <div><p class="text-sm font-medium text-zinc-400">Private filesystem boundary</p><h2 class="text-xl font-semibold text-white">Four approved logical disks</h2></div>
                <p class="text-xs text-zinc-500">No absolute roots or public URLs displayed</p>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($disks as $disk)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <div class="flex items-center justify-between gap-3"><code class="text-sm font-semibold text-white">{{ $disk['name'] }}</code><span class="text-xs font-semibold text-emerald-300">{{ $disk['healthy'] ? 'HEALTHY' : 'CHECK' }}</span></div>
                        <p class="mt-3 text-sm leading-6 text-zinc-300">{{ $disk['purpose'] }}</p>
                        <div class="mt-4 flex gap-2 text-[11px]"><span class="rounded bg-zinc-800 px-2 py-1 text-zinc-300">Private</span><span class="rounded bg-zinc-800 px-2 py-1 text-zinc-300">No URL exposure</span></div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <p class="text-sm font-medium text-zinc-400">Stable identity contract</p><h2 class="mt-1 text-xl font-semibold text-white">Media type prefixes and sequence format</h2>
                <div class="mt-5 overflow-hidden rounded-lg border border-zinc-700">
                    @foreach ($idExamples as $example)
                        <div class="grid grid-cols-[0.8fr_0.45fr_1fr] border-b border-zinc-700 bg-zinc-800 px-4 py-3 last:border-b-0">
                            <span class="text-sm capitalize text-zinc-300">{{ $example['type'] }}</span><code class="text-sm text-emerald-300">{{ $example['prefix'] }}</code><code class="text-sm text-white">{{ $example['example'] }}</code>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-zinc-500">Production allocation uses a transaction and row lock. Displayed values are fictional examples only.</p>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <p class="text-sm font-medium text-zinc-400">Bucket boundaries</p><h2 class="mt-1 text-xl font-semibold text-white">Deterministic distribution</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($bucketExamples as $example)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-800 p-4"><code class="text-white">{{ $example['archive_id'] }}</code><p class="mt-2 text-sm text-zinc-400">Bucket <span class="font-semibold text-emerald-300">{{ $example['bucket'] }}</span></p></div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
            <p class="text-sm font-medium text-zinc-400">Original preservation boundary</p><h2 class="mt-1 text-xl font-semibold text-white">Original and derivative paths remain separate</h2>
            <div class="mt-5 overflow-x-auto rounded-lg border border-zinc-700">
                <table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-zinc-800 text-zinc-400"><tr><th class="px-4 py-3">Version</th><th class="px-4 py-3">Logical disk</th><th class="px-4 py-3">Relative path</th></tr></thead><tbody>
                @foreach ($pathExamples as $example)<tr class="border-t border-zinc-700"><td class="px-4 py-3 text-white">{{ $example['label'] }}</td><td class="px-4 py-3"><code class="text-emerald-300">{{ $example['disk']->value }}</code></td><td class="px-4 py-3"><code class="text-zinc-300">{{ $example['path'] }}</code></td></tr>@endforeach
                </tbody></table>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6"><p class="text-sm font-medium text-zinc-400">Quarantine and future manifests</p><h2 class="mt-1 text-xl font-semibold text-white">Path planning only</h2><div class="mt-5 space-y-3">@foreach ($plannedPaths as $example)<div class="rounded-lg border border-zinc-700 bg-zinc-800 p-4"><div class="flex justify-between gap-3"><span class="text-sm text-white">{{ $example['label'] }}</span><code class="text-xs text-emerald-300">{{ $example['disk']->value }}</code></div><code class="mt-2 block break-all text-xs text-zinc-300">{{ $example['path'] }}</code></div>@endforeach</div></article>
            <article class="rounded-xl border border-amber-800 bg-amber-950/20 p-6"><p class="text-sm font-medium text-amber-300">Path security rejection proof</p><h2 class="mt-1 text-xl font-semibold text-white">Unsafe forms are rejected</h2><div class="mt-5 space-y-2">@foreach ($rejections as $rejection)<div class="rounded-lg border border-amber-900/70 bg-zinc-950/40 p-3"><code class="block break-all text-xs text-zinc-200">{{ $rejection['candidate'] }}</code><p class="mt-1 text-xs text-amber-300">{{ $rejection['result'] }}</p></div>@endforeach</div></article>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6"><p class="text-sm font-medium text-zinc-400">Access boundary</p><h2 class="mt-1 text-xl font-semibold text-white">Owner-only and read-only</h2><div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="rounded-lg bg-zinc-800 p-4"><p class="text-sm text-zinc-400">Verified Owner</p><p class="mt-2 font-semibold text-emerald-300">HTTP 200</p></div><div class="rounded-lg bg-zinc-800 p-4"><p class="text-sm text-zinc-400">Non-owner</p><p class="mt-2 font-semibold text-amber-300">HTTP 403</p></div><div class="rounded-lg bg-zinc-800 p-4"><p class="text-sm text-zinc-400">Guest</p><p class="mt-2 font-semibold text-amber-300">Redirect to login</p></div></div></article>
            <article class="rounded-xl border border-emerald-800 bg-emerald-950/30 p-6"><p class="text-sm font-medium text-emerald-300">Registered middleware</p><div class="mt-4 flex flex-wrap gap-2">@foreach ($routeMiddleware as $middleware)<code class="rounded bg-zinc-950/60 px-3 py-2 text-xs text-zinc-200">{{ $middleware }}</code>@endforeach</div><p class="mt-5 text-sm leading-6 text-zinc-300">This owner-only status surface is read-only. Storage mutation remains restricted to validated application workflows and explicit console operations.</p></article>
        </section>
    </div>
</x-layouts::app>
