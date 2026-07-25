<x-layouts::app :title="__('Dashboard')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                Family Archive
            </p>

            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Dashboard
            </h1>

            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                A private, review-first family preservation platform.
            </p>
        </div>

        <section class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 sm:grid-cols-3">
            <div><p class="text-xs uppercase tracking-wide text-zinc-500">Release</p><p class="mt-1 font-semibold text-zinc-950 dark:text-white">v{{ \App\Support\Release::version() }}</p></div>
            <div><p class="text-xs uppercase tracking-wide text-zinc-500">Name</p><p class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ \App\Support\Release::name() }}</p></div>
            <div><p class="text-xs uppercase tracking-wide text-zinc-500">Roadmap groups</p><p class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ \App\Support\Release::groups() }}</p></div>
        </section>

        @if (auth()->user()?->role === 'owner')
            <a
                href="{{ route('admin.dashboard') }}"
                class="inline-flex w-fit items-center rounded-lg bg-zinc-950 px-5 py-3
                       text-sm font-semibold text-white transition hover:bg-zinc-800
                       dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200"
            >
                Open Archive Administration
            </a>
        @endif
    </div>
</x-layouts::app>
