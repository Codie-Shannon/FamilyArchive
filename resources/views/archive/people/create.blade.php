<x-layouts::app title="Add Reviewed Person">
    <main class="mx-auto max-w-4xl space-y-6 p-6">
        <header>
            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-300">Group 14 review</p>
            <h1 class="text-3xl font-semibold text-zinc-950 dark:text-white">Add reviewed person</h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300">Record only the name and life-date precision supported by the reviewed evidence.</p>
        </header>
        <form method="POST" action="{{ route('archive.people.store') }}" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @include('archive.people._form')
            <div class="mt-6 flex gap-3"><button class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Create reviewed person</button><a href="{{ route('archive.people.index') }}" class="px-4 py-3">Cancel</a></div>
        </form>
    </main>
</x-layouts::app>
