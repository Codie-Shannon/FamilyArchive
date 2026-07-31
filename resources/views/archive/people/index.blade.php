<x-layouts::app title="Reviewed People">
    <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-6">
        <x-archive-navigation />

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-300">{{ config('release.status') }}</p>
                <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Reviewed people</h1>
                <p class="mt-2 max-w-3xl text-zinc-600 dark:text-zinc-300">Browse accepted identities while preserving uncertain names, incomplete life dates and sensitive-person boundaries.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('archive.branches.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-600 dark:text-white">Family branches</a>
                <a href="{{ route('archive.people.create') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-black">Add reviewed person</a>
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($people as $person)
                <a href="{{ route('archive.people.show', $person) }}" class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-zinc-500">{{ $person->person_id }}</p>
                            <h2 class="mt-2 text-xl font-semibold text-zinc-950 dark:text-white">{{ $personPresenter->browseName($person) }}</h2>
                        </div>
                        @if($person->is_private)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100">Sensitive</span>@endif
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $personPresenter->lifeDates($person) }}</p>
                    @if($person->familyBranch)
                        <p class="mt-3 text-sm text-zinc-500">{{ $branchPresenter->browseName($person->familyBranch) }}</p>
                    @else
                        <p class="mt-3 text-sm text-zinc-500">No reviewed branch</p>
                    @endif
                    <p class="mt-4 text-xs text-zinc-500">{{ str($person->name_certainty->value)->headline() }} name · {{ $person->provenance_links_count }} sources</p>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300 md:col-span-2 xl:col-span-3">
                    No reviewed people yet. Add the first identity from human-reviewed source evidence.
                </div>
            @endforelse
        </section>

        {{ $people->links() }}
    </main>
</x-layouts::app>
