<x-layouts::app title="Edit Family Branch">
    <main class="mx-auto max-w-3xl space-y-6 p-6">
        <header><p class="font-mono text-xs text-zinc-500">{{ $branch->branch_id }}</p><h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Review family branch revision</h1><p class="mt-2 text-zinc-600 dark:text-zinc-300">Saving appends immutable branch evidence.</p></header>
        <form method="POST" action="{{ route('archive.branches.update', $branch) }}" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">@csrf @method('PATCH')<input type="hidden" name="expected_metadata_revision" value="{{ $branch->metadata_revision }}">@include('archive.branches._form')<div class="mt-6 flex gap-3"><button class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Save reviewed revision</button><a href="{{ route('archive.branches.show', $branch) }}" class="px-4 py-3">Cancel</a></div></form>
    </main>
</x-layouts::app>
