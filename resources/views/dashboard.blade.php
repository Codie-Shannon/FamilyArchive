<x-layouts::app :title="__('Home')">
<main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-8">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-300">Your family archive</p>
            <h1 class="mt-1 text-3xl font-semibold text-white">Welcome back, {{ str($user->name)->before(' ') }}</h1>
            <p class="mt-2 max-w-2xl text-zinc-400">See what is new, continue a contribution and reach the parts of the archive available to you.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('archive.index') }}" class="rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-zinc-950">Browse archive</a>
            @if($user->canContribute())
                <a href="{{ route('contributor.index') }}" class="rounded-xl border border-zinc-700 px-5 py-3 text-sm font-semibold text-white">Contribute media</a>
            @endif
        </div>
    </header>

    <section aria-label="Member summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">Archive</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ $photos->total() }}</p>
            <p class="mt-1 text-sm text-zinc-400">approved photos available</p>
        </article>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">Community</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ $communitySpaces }}</p>
            <p class="mt-1 text-sm text-zinc-400">family {{ Str::plural('space', $communitySpaces) }}</p>
        </article>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">Contributions</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ $uploadSessions->count() }}</p>
            <p class="mt-1 text-sm text-zinc-400">recent upload {{ Str::plural('session', $uploadSessions->count()) }}</p>
        </article>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">Your access</p>
            <p class="mt-3 text-lg font-semibold text-white">{{ str($user->role)->headline() }}</p>
            <p class="mt-1 text-sm text-emerald-300">Approved account</p>
        </article>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
            <div class="flex items-end justify-between gap-4">
                <div><p class="text-sm font-medium text-emerald-300">Recently available</p><h2 class="mt-1 text-xl font-semibold text-white">From the family archive</h2></div>
                <a href="{{ route('archive.index') }}" class="text-sm font-semibold text-emerald-300">View all</a>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @forelse($photos as $photo)
                    <a href="{{ url('/archive/photos/'.$photo->mediaItemId) }}" class="group overflow-hidden rounded-xl border border-zinc-700 bg-zinc-950">
                        <div class="aspect-[16/8] bg-black">
                            @if($photo->thumbnailVersionId)
                                <img src="{{ url('/archive/derivatives/'.$photo->thumbnailVersionId.'/preview') }}" alt="Private thumbnail for {{ $photo->archiveId }}" class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                            @else
                                <div class="flex h-full items-center justify-center text-sm text-zinc-500">Private preview unavailable</div>
                            @endif
                        </div>
                        <div class="p-4"><p class="text-xs font-semibold text-emerald-300">{{ $photo->archiveId }}</p><p class="mt-1 font-semibold text-white">{{ $photo->title }}</p></div>
                    </a>
                @empty
                    <p class="sm:col-span-2 rounded-xl border border-dashed border-zinc-700 p-6 text-zinc-400">No approved photos are available yet.</p>
                @endforelse
            </div>
        </article>

        <div class="space-y-5">
            <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-sm font-medium text-emerald-300">Family activity</p>
                <h2 class="mt-1 text-xl font-semibold text-white">Community in one place</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-400">Open your family spaces, see approved voice notes and catch up with recent activity.</p>
                <a href="{{ route('community.index') }}" class="mt-5 inline-flex rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Open family activity</a>
            </article>
            <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-sm font-medium text-cyan-300">Private messages</p>
                <h2 class="mt-1 text-xl font-semibold text-white">Consent-first conversations</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-400">Review message requests and private attachment status without exposing archive originals.</p>
                <a href="{{ route('secure-messages.index') }}" class="mt-5 inline-flex rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Open messages</a>
            </article>
            @if($user->role === 'owner')
                <article class="rounded-2xl border border-amber-800 bg-amber-950/20 p-5">
                    <p class="text-sm font-medium text-amber-300">Owner tools</p>
                    <p class="mt-2 text-sm text-amber-100">Archive administration remains available while its command centre is prepared for SG17.</p>
                    <a href="{{ route('admin.dashboard') }}" class="mt-4 inline-flex text-sm font-semibold text-amber-200">Manage archive →</a>
                </article>
            @endif
        </div>
    </section>
</main>
</x-layouts::app>
