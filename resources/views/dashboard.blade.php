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
                The archive foundation is installed and ready for its first domain features.
            </p>
        </div>

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