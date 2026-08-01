<x-layouts::app title="Work">
<main class="mx-auto w-full max-w-7xl space-y-7 p-4 md:p-8">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Role-aware workspace</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Your work, in one place</h1>
            <p class="mt-2 max-w-3xl text-zinc-400">
                @if($user->role === 'owner')
                    Routine work stays delegated. This view brings policy, original-access and preservation exceptions forward without making you approve every family action.
                @elseif($user->role === 'admin')
                    Handle ordinary family and intake decisions here. Elevated roles, original access and policy changes remain with the Owner.
                @else
                    Finish the batches you contributed. You do not need an Owner to approve each routine item.
                @endif
            </p>
        </div>
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500">Current responsibility</p>
            <p class="mt-1 font-semibold text-white">{{ $roleLabel }}</p>
        </div>
    </header>

    <section aria-label="Role work summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $card)
            @php($classes = match($card['tone']) {
                'amber' => 'border-amber-800 bg-amber-950/20',
                'emerald' => 'border-emerald-900 bg-emerald-950/20',
                'sky' => 'border-sky-900 bg-sky-950/20',
                default => 'border-zinc-700 bg-zinc-900',
            })
            <a href="{{ $card['route'] }}" class="rounded-2xl border {{ $classes }} p-5 transition hover:border-emerald-600">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="font-semibold text-white">{{ $card['label'] }}</h2>
                    @if($card['count'] > 0)<span class="rounded-full bg-zinc-950 px-3 py-1 text-sm font-semibold text-white">{{ $card['count'] }}</span>@endif
                </div>
                <p class="mt-3 text-sm leading-6 text-zinc-400">{{ $card['detail'] }}</p>
                <p class="mt-5 text-sm font-semibold text-emerald-300">Open →</p>
            </a>
        @endforeach
    </section>

    <section class="grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
            <p class="text-sm font-semibold text-emerald-300">Responsibility boundary</p>
            <h2 class="mt-1 text-xl font-semibold text-white">The right person makes the decision</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Members</p><p class="mt-2 text-sm text-zinc-300">Publish routine family conversation and decide their own message requests.</p></div>
                <div class="rounded-xl bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Administrators</p><p class="mt-2 text-sm text-zinc-300">Handle ordinary accounts, reported content and delegated intake review.</p></div>
                <div class="rounded-xl bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Owner</p><p class="mt-2 text-sm text-zinc-300">Controls elevated roles, original access, policy and preservation exceptions.</p></div>
            </div>
        </article>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
            <p class="text-sm font-semibold text-sky-300">Less navigation</p>
            <h2 class="mt-1 text-xl font-semibold text-white">One operational starting point</h2>
            <p class="mt-3 text-sm leading-6 text-zinc-400">The sidebar now exposes one Work destination. Specialist pages open only when a task needs them, keeping everyday archive browsing separate from administration.</p>
            <a href="{{ route('dashboard') }}" class="mt-5 inline-flex rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Back to Home</a>
        </article>
    </section>
</main>
</x-layouts::app>
