<x-layouts::app :title="__('Private Archive')">
<x-archive-shell>
    <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div><p class="text-sm font-medium text-emerald-300">Access-filtered family archive</p><h1 class="text-3xl font-semibold text-white">Photos</h1><p class="mt-2 max-w-2xl text-zinc-400">Browse preservation-safe copies allowed by your role and family branch. Originals require a separate active grant.</p></div>
        <div class="flex items-center gap-3">@if(auth()->user()?->role === 'owner')<a href="{{ route('archive.sources.index') }}" class="rounded-xl border border-zinc-700 px-4 py-3 text-sm text-emerald-300">Source provenance</a>@endif<div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100"><strong>{{ $photos->total() }}</strong> approved archive records</div></div>
    </header>
    <section class="rounded-xl border border-amber-700 bg-amber-950/25 p-5 text-sm text-amber-100"><strong>Read-only preservation boundary:</strong> browsing never generates files, changes metadata, exposes storage locations or falls back to original pixels.</section>
    <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($photos as $photo)
        @include('archive._photo-card', ['photo' => $photo])
        @empty
        <div class="col-span-full rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center text-zinc-400">No approved photos are available for private browsing.</div>
        @endforelse
    </section>
    <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">{{ $photos->links() }}</div>
    <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 text-sm text-zinc-400"><strong class="text-white">No mutation controls:</strong> no download, edit, delete, replace, approve, share, select or bulk-action controls exist in this archive.</section>
</x-archive-shell>
</x-layouts::app>
