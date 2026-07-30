<x-layouts::app title="Add Family Branch">
    <main class="mx-auto max-w-3xl space-y-6 p-6">
        <header><p class="text-sm font-semibold text-emerald-600 dark:text-emerald-300">Group 14 review</p><h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Add family branch</h1><p class="mt-2 text-zinc-600 dark:text-zinc-300">Create a reviewed grouping only; individual relationships remain outside this group.</p></header>
        <form method="POST" action="{{ route('archive.branches.store') }}" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">@csrf @include('archive.branches._form')<div class="mt-6 flex gap-3"><button class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Create family branch</button><a href="{{ route('archive.branches.index') }}" class="px-4 py-3">Cancel</a></div></form>
    </main>
</x-layouts::app>
