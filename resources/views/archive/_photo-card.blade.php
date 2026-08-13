@php($selectable = $selectable ?? false)
@php($selected = $selected ?? false)
@php($returnTo = $returnTo ?? request()->getRequestUri())
<article data-photo-card data-photo-id="{{ $photo->mediaItemId }}" class="group relative overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-xl shadow-black/10 {{ $selected ? 'ring-2 ring-emerald-400' : '' }}">
    @if($selectable)<label data-photo-selector class="absolute right-3 top-3 z-20 hidden cursor-pointer rounded-lg bg-zinc-950/90 p-2 shadow-lg"><input type="checkbox" data-photo-checkbox value="{{ $photo->mediaItemId }}" @checked($selected) class="h-6 w-6 rounded border-zinc-500 bg-zinc-950 text-emerald-500 focus:ring-emerald-500"><span class="sr-only">Select {{ $photo->archiveId }}</span></label>@endif
    <a href="{{ route('archive.photos.show', ['mediaItem' => $photo->mediaItemId, 'return_to' => $returnTo ?? request()->getRequestUri()]) }}" data-photo-link class="block">
        <div class="aspect-[4/3] bg-zinc-950">@if($photo->thumbnailVersionId)<img src="{{ route('archive.derivatives.preview', $photo->thumbnailVersionId) }}" alt="Private thumbnail for {{ $photo->archiveId }}" class="h-full w-full object-cover">@else<div class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center text-zinc-500"><span class="text-4xl">◇</span><strong class="text-zinc-300">Preview unavailable</strong><span class="text-xs">No original fallback</span></div>@endif</div>
        <div class="space-y-3 p-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-emerald-300">{{ $photo->archiveId }}</p><h2 class="mt-1 text-lg font-semibold text-white">{{ $photo->title }}</h2></div></div>
    </a>
</article>
