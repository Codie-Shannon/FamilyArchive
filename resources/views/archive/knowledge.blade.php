<x-layouts::app title="Archive Knowledge">
    <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-6">
        <x-archive-navigation />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Reviewed family discovery</p>
                <h1 class="text-3xl font-semibold text-white">Archive Knowledge</h1>
                <p class="mt-2 text-zinc-400">Search the parts of family history your account is permitted to see.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($counts as $label => $count)
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm capitalize text-zinc-400">{{ str($label)->replace('_', ' ') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-white">{{ number_format($count) }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
            <h2 class="text-xl font-semibold text-white">Permission-aware search</h2>
            <form class="mt-4 flex gap-3">
                <input name="q" value="{{ $query }}" maxlength="100" placeholder="Search people, events or safe locations" class="min-w-0 flex-1 rounded-lg bg-zinc-950 p-3 text-white">
                <button class="rounded-lg bg-emerald-500 px-5 font-semibold text-black">Search</button>
            </form>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                @forelse($results as $result)
                    <article class="rounded-lg border border-zinc-700 p-4">
                        <p class="text-xs uppercase tracking-wide text-emerald-300">{{ $result->entity_type }}</p>
                        <h3 class="mt-1 font-semibold text-white">{{ $result->label }}</h3>
                        <p class="font-mono text-xs text-zinc-500">{{ $result->stable_id }}</p>
                    </article>
                @empty
                    <p class="text-zinc-400">{{ $query === '' ? 'Enter a search term to explore reviewed archive knowledge.' : 'No accessible records matched.' }}</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-5 text-sm text-emerald-100">
            Living people, sensitive locations, private identities and unreviewed records are filtered before display. Owners and archive administrators retain controlled curation access without making ordinary browsing an approval task.
        </aside>
    </main>
</x-layouts::app>
