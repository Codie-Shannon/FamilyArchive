<x-layouts::app title="Add Reviewed Location">
    <main class="mx-auto max-w-3xl space-y-6 p-6">
        <header><p class="text-sm font-semibold text-emerald-600 dark:text-emerald-300">Archive place</p><h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Add reviewed place</h1><p class="mt-2 text-zinc-600 dark:text-zinc-300">Give the place a recognizable title, then record its geographic address separately. Sensitive places must use private browse precision.</p></header>
        <form method="POST" action="{{ route('archive.locations.store') }}" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @include('archive.locations._form')
            <div class="mt-6 flex gap-3"><button class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Create reviewed place</button><a href="{{ route('archive.locations.index') }}" class="px-4 py-3">Cancel</a></div>
        </form>
    </main>
</x-layouts::app>
