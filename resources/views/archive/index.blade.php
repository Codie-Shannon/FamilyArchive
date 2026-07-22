<x-layouts::app :title="__('Private Archive')">
<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-8">
    <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div><p class="text-sm font-medium text-emerald-300">Owner-only private archive</p><h1 class="text-3xl font-semibold text-white">Approved family photos</h1><p class="mt-2 max-w-2xl text-zinc-400">Browse preservation-safe thumbnail derivatives. Originals remain private, verified and inaccessible.</p></div>
        <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100"><strong>{{ $photos->total() }}</strong> approved archive records</div>
    </header>
    <section class="rounded-xl border border-amber-700 bg-amber-950/25 p-5 text-sm text-amber-100"><strong>Read-only preservation boundary:</strong> browsing never generates files, changes metadata, exposes storage locations or falls back to original pixels.</section>
    <section class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($photos as $photo)
        <article class="overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-xl shadow-black/10">
            <a href="{{ url('/archive/photos/'.$photo->mediaItemId) }}" class="block">
                <div class="aspect-[4/3] bg-zinc-950">
                    @if($photo->thumbnailVersionId)
                        <img src="{{ url('/archive/derivatives/'.$photo->thumbnailVersionId.'/preview') }}" alt="Private thumbnail for {{ $photo->archiveId }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center text-zinc-500"><span class="text-4xl">◇</span><strong class="text-zinc-300">Derivative unavailable</strong><span class="text-xs">No original fallback</span></div>
                    @endif
                </div>
                <div class="space-y-3 p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-emerald-300">{{ $photo->archiveId }}</p><h2 class="mt-1 text-lg font-semibold text-white">{{ $photo->title }}</h2></div><div class="flex flex-wrap gap-2 text-xs"><span class="rounded-full border border-zinc-700 px-2.5 py-1 text-zinc-300">{{ str_replace('_', ' ', $photo->thumbnailStatus) }}</span><span class="rounded-full border border-emerald-900 px-2.5 py-1 text-emerald-200">{{ $photo->preservationStatus }}</span></div></div>
            </a>
        </article>
        @empty
        <div class="col-span-full rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center text-zinc-400">No approved photos are available for private browsing.</div>
        @endforelse
    </section>
    <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">{{ $photos->links() }}</div>
    <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 text-sm text-zinc-400"><strong class="text-white">No mutation controls:</strong> no download, edit, delete, replace, approve, share, select or bulk-action controls exist in this archive.</section>
</div>
</x-layouts::app>
