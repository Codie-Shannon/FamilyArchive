<x-layouts::app :title="__('Archive Storage')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Owner administration</p>
                    <h1 class="mt-2 text-3xl font-semibold text-white">Archive Storage Foundation</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-300">Read-only contracts for stable IDs, private logical disks and deterministic relative paths. This surface does not write, copy, move, replace, regenerate or delete media bytes.</p>
                </div>
                <span class="rounded-full border border-emerald-700 bg-emerald-950/50 px-4 py-2 text-sm font-semibold text-emerald-300">Contract health: healthy</span>
            </div>
        </header>

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
            <article class="rounded-xl border border-emerald-800 bg-emerald-950/30 p-6"><p class="text-sm font-medium text-emerald-300">Registered middleware</p><div class="mt-4 flex flex-wrap gap-2">@foreach ($routeMiddleware as $middleware)<code class="rounded bg-zinc-950/60 px-3 py-2 text-xs text-zinc-200">{{ $middleware }}</code>@endforeach</div><p class="mt-5 text-sm leading-6 text-zinc-300">Zero storage mutation controls. No upload, write, copy, move, replace, regenerate or delete action is registered.</p></article>
        </section>
    </div>
</x-layouts::app>
