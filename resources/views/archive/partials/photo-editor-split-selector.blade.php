@if(count($splitFamily) > 1)
    <section data-split-selector>
        <div class="mb-2">
            <h2 class="font-semibold text-white">Saved photos from this source</h2>
            <p class="text-sm text-zinc-400">Choose a saved split to edit it. These photos stay grouped beneath their source and do not appear separately in the batch selector.</p>
        </div>
        <nav data-editor-filmstrip data-filmstrip-key="splits:{{ collect($splitFamily)->pluck('id')->join('-') }}" class="flex gap-3 overflow-x-auto rounded-xl border border-amber-800 bg-amber-950/15 p-3" aria-label="Saved split photos">
            @foreach($splitFamily as $sibling)
                <a href="{{ $editorUrl($batchCurrent, $sibling['id']) }}" data-editor-navigation data-active-photo="{{ $sibling['current'] ? 'true' : 'false' }}" data-split-photo-id="{{ $sibling['id'] }}" class="w-36 shrink-0 overflow-hidden rounded-xl border {{ $sibling['current'] ? 'border-emerald-400 bg-emerald-950/30' : 'border-zinc-700 bg-zinc-900' }}" title="Edit {{ $sibling['title'] }}">
                    <div class="flex aspect-square items-center justify-center bg-zinc-950">
                        @if($sibling['thumbnail_version_id'])
                            <img src="{{ route('archive.derivatives.preview', $sibling['thumbnail_version_id']) }}" alt="{{ $sibling['title'] }}" class="size-full object-cover">
                        @else
                            <span class="text-sm text-zinc-500">Preview unavailable</span>
                        @endif
                    </div>
                    <div class="truncate p-2 text-xs text-zinc-300">{{ $sibling['archive_id'] }}</div>
                </a>
            @endforeach
        </nav>
    </section>
@endif
