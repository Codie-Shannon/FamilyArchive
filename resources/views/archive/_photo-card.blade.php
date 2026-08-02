<article class="overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-xl shadow-black/10">
    <a href="{{ route('archive.photos.show', $photo->mediaItemId) }}" class="block">
        <div class="aspect-[4/3] bg-zinc-950">
            @if($photo->thumbnailVersionId)
                <img src="{{ route('archive.derivatives.preview', $photo->thumbnailVersionId) }}" alt="Private thumbnail for {{ $photo->archiveId }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center text-zinc-500">
                    <span class="text-4xl">◇</span>
                    <strong class="text-zinc-300">Preview unavailable</strong>
                    <span class="text-xs">No original fallback</span>
                </div>
            @endif
        </div>
        <div class="space-y-3 p-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-300">{{ $photo->archiveId }}</p>
                <h2 class="mt-1 text-lg font-semibold text-white">{{ $photo->title }}</h2>
            </div>
        </div>
    </a>
</article>
