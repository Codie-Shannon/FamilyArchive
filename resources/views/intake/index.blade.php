<x-layouts::app title="Intake & Review">
<main class="mx-auto w-full max-w-7xl space-y-7 p-4 md:p-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-emerald-300">Trusted preservation workflow</p>
            <h1 class="mt-1 text-3xl font-semibold text-white">Intake & Review</h1>
            <p class="mt-2 max-w-3xl text-zinc-400">Import a batch, inspect automatic suggestions and finish the batch from one review workspace. Originals remain immutable throughout.</p>
        </div>
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-3 text-sm text-zinc-300">Owner · Admin · Trusted contributor</div>
    </header>

    <section class="grid grid-cols-3 gap-2 sm:gap-4">
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:rounded-2xl sm:p-5"><p class="text-xs text-zinc-400 sm:text-sm">Batches</p><p class="mt-1 text-2xl font-semibold text-white sm:mt-2 sm:text-3xl">{{ number_format($sessions->count()) }}</p></article>
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:rounded-2xl sm:p-5"><p class="text-xs leading-tight text-zinc-400 sm:text-sm">Awaiting decisions</p><p class="mt-1 text-2xl font-semibold text-amber-300 sm:mt-2 sm:text-3xl">{{ number_format($sessions->sum('pending_count')) }}</p></article>
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:rounded-2xl sm:p-5"><p class="text-xs leading-tight text-zinc-400 sm:text-sm">Need attention</p><p class="mt-1 text-2xl font-semibold text-white sm:mt-2 sm:text-3xl">{{ number_format($sessions->sum('attention_count')) }}</p></article>
    </section>

    <section class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-xl font-semibold text-white">Photo batches</h2><p class="mt-1 text-sm text-zinc-400">Resume at the last checkpoint without reopening completed files.</p></div><span class="text-xs uppercase tracking-wide text-emerald-300">Exception-first review</span></div>
        <div class="mt-5 space-y-3">
            @forelse($sessions as $session)
                @php($progress = $session->imported_count > 0 ? (int) round(($session->reviewed_count / $session->imported_count) * 100) : 0)
                <a href="{{ route('intake.batches.show', $session->session_id) }}" class="block rounded-xl border border-zinc-700 bg-zinc-950 p-5 transition hover:border-emerald-700">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">{{ str($session->review_state)->replace('_', ' ') }}</p><h3 class="mt-1 truncate text-lg font-semibold text-white">{{ $session->manifest['source_label'] ?? 'Private photo batch' }}</h3><p class="mt-1 text-xs text-zinc-500">{{ number_format($session->imported_count) }} retained · {{ number_format($session->reviewed_count) }} reviewed · {{ number_format($session->attention_count) }} need attention</p></div>
                        <strong class="text-2xl text-white">{{ $progress }}%</strong>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-800"><div class="h-full bg-emerald-500" style="width: {{ min(100, $progress) }}%"></div></div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-700 p-8 text-center text-zinc-400">No trusted-intake batch is ready yet. Inventory a directory with the resumable batch importer, then return here.</div>
            @endforelse
        </div>
    </section>
</main>
</x-layouts::app>
