<x-layouts.public-discovery title="Public Showcase">
    <main class="mx-auto max-w-7xl space-y-9 px-6 py-10">
        <header class="grid gap-6 rounded-3xl border border-emerald-950 bg-gradient-to-br from-emerald-950/60 to-zinc-900 p-8 lg:grid-cols-[1.4fr_0.6fr]">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Public family stories</p>
                <h1 class="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-white">Stories approved for everyone</h1>
                <p class="mt-4 max-w-2xl text-lg leading-8 text-zinc-300">A deliberately small public window into the archive. Every story is reviewed, approved and separable from private family records.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="#published-stories" class="rounded-lg bg-emerald-400 px-5 py-3 font-semibold text-zinc-950">Explore approved stories</a>
                    <a href="{{ route('public-discovery.map') }}" class="rounded-lg border border-zinc-600 px-5 py-3 text-zinc-200">Open privacy-safe map</a>
                </div>
            </div>
            <aside class="self-end rounded-2xl border border-zinc-700 bg-zinc-950/70 p-6">
                <p class="text-sm text-zinc-500">Public release</p>
                <p class="mt-2 text-xl font-semibold text-white">v{{ \App\Support\Release::version() }}</p>
                <p class="mt-1 text-emerald-300">{{ \App\Support\Release::name() }}</p>
                <p class="mt-5 text-sm leading-6 text-zinc-400">Drafts, review candidates, living-person details and exact coordinates never appear here.</p>
            </aside>
        </header>

        <section id="published-stories" aria-labelledby="published-stories-heading" class="space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-emerald-300">Reviewed collection</p>
                    <h2 id="published-stories-heading" class="mt-1 text-2xl font-semibold text-white">Published archive stories</h2>
                </div>
                <p class="text-sm text-zinc-500">{{ $entries->count() }} approved {{ Str::plural('story', $entries->count()) }}</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse($entries as $entry)
                    <article class="flex min-h-56 flex-col rounded-2xl border border-zinc-800 bg-zinc-900 p-6">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-emerald-950 px-3 py-1 text-xs uppercase tracking-wide text-emerald-300">Published</span>
                            <span class="text-xs text-zinc-500">{{ $entry->allow_social_cards ? 'Social card approved' : 'Archive only' }}</span>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold text-white">{{ $entry->public_title }}</h3>
                        <p class="mt-3 leading-7 text-zinc-400">{{ $entry->public_summary }}</p>
                        <p class="mt-auto pt-6 text-sm text-zinc-600">Reviewed public archive material</p>
                    </article>
                @empty
                    <p class="rounded-2xl border border-dashed border-zinc-700 p-6 text-zinc-400 md:col-span-2 xl:col-span-3">No stories have been published yet.</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-2xl border border-amber-900 bg-amber-950/20 p-5 text-amber-100">
            Public discovery is opt-in and review-first. Publication never widens access to the private archive, originals or source provenance.
        </aside>
    </main>
</x-layouts.public-discovery>
