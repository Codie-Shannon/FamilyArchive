<x-layouts::app title="Batch review">
<main class="mx-auto w-full max-w-[1600px] space-y-5 p-4 md:space-y-6 md:p-8">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div><a href="{{ route('intake.index') }}" class="text-sm font-semibold text-emerald-300">← Intake & Review</a><p class="mt-4 text-sm font-semibold text-zinc-400">{{ $manifest['source_label'] ?? 'Private photo batch' }}</p><h1 class="mt-1 text-3xl font-semibold text-white">Review batch</h1><p class="mt-2 max-w-3xl text-zinc-400">Compare the retained original with the suggested edit, handle exceptions first and apply decisions in bulk.</p></div>
        @if($session->state === 'complete')
            <form method="POST" action="{{ route('intake.batches.prepare', $session->session_id) }}" class="w-full sm:w-auto">@csrf<button class="w-full rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-zinc-950 sm:w-auto">Prepare next 25 previews</button></form>
        @else
            <div class="rounded-xl border border-amber-800 bg-amber-950/30 px-4 py-3 text-sm text-amber-100">Upload still open · finish it before review</div>
        @endif
    </header>

    @if(session('status'))<div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>@endif

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-5">
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:p-4"><p class="text-[11px] uppercase text-zinc-500 sm:text-xs">Retained</p><p class="mt-1 text-xl font-semibold text-white sm:text-2xl">{{ number_format($session->imported_count) }}</p></article>
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:p-4"><p class="text-[11px] uppercase text-zinc-500 sm:text-xs">Prepared</p><p class="mt-1 text-xl font-semibold text-white sm:text-2xl">{{ number_format($preparedCount) }}</p></article>
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:p-4"><p class="text-[11px] uppercase text-zinc-500 sm:text-xs">Reviewed</p><p class="mt-1 text-xl font-semibold text-emerald-300 sm:text-2xl">{{ number_format($session->reviewed_count) }}</p></article>
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:p-4"><p class="text-[11px] uppercase text-zinc-500 sm:text-xs">Attention</p><p class="mt-1 text-xl font-semibold text-amber-300 sm:text-2xl">{{ number_format($session->attention_count) }}</p></article>
        <article class="col-span-2 rounded-xl border border-zinc-700 bg-zinc-900 p-3 sm:p-4 xl:col-span-1"><p class="text-[11px] uppercase text-zinc-500 sm:text-xs">Workflow</p><p class="mt-1 text-base font-semibold capitalize text-white sm:text-lg">{{ str($session->review_state)->replace('_', ' ') }}</p></article>
    </section>

    <nav class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap" aria-label="Review filters">
        @foreach(['pending' => 'Pending', 'attention' => 'Needs attention', 'reviewed' => 'Reviewed', 'all' => 'All'] as $key => $label)
            <a href="{{ route('intake.batches.show', [$session->session_id, 'filter' => $key]) }}" class="rounded-full border px-3 py-2 text-center text-xs font-semibold sm:px-4 sm:text-sm {{ $filter === $key ? 'border-emerald-500 bg-emerald-950 text-emerald-200' : 'border-zinc-700 text-zinc-300' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('intake.batches.review', $session->session_id) }}" class="hidden space-y-5 sm:block">
        @csrf @method('PATCH')
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-700 bg-zinc-900 p-4"><label class="flex items-center gap-3 text-sm font-semibold text-white"><input id="select-visible" type="checkbox" class="size-4 rounded border-zinc-600 bg-zinc-950 text-emerald-500"> Select visible items</label><p class="text-xs text-zinc-400">Up to 24 decisions are committed together. Failed items are isolated without losing successful work.</p></div>

        <section class="grid gap-5 xl:grid-cols-2">
            @forelse($items as $item)
                <article class="overflow-hidden rounded-2xl border {{ $item->attention_code ? 'border-amber-700' : 'border-zinc-700' }} bg-zinc-900">
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-700 px-4 py-3"><label class="flex min-w-0 items-center gap-3"><input name="items[]" value="{{ $item->id }}" type="checkbox" class="batch-item size-4 shrink-0 rounded border-zinc-600 bg-zinc-950 text-emerald-500" {{ $item->review_decision ? 'disabled' : '' }}><span class="truncate font-semibold text-white">{{ $item->original_name }}</span></label><div class="flex shrink-0 gap-2">@if($item->attention_code)<span class="rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-200">{{ str($item->attention_code)->replace('_', ' ') }}</span>@endif @if($item->review_decision)<span class="rounded-full bg-emerald-950 px-2 py-1 text-xs font-semibold text-emerald-200">{{ str($item->review_decision)->replace('_', ' ') }}</span>@endif</div></div>
                    <div class="grid min-h-72 grid-cols-2 bg-zinc-950">
                        <a target="_blank" href="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" class="relative border-r border-zinc-700"><img src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" alt="Retained original" class="h-72 w-full object-contain"><span class="absolute left-2 top-2 rounded bg-zinc-950/80 px-2 py-1 text-xs font-semibold text-white">Original</span></a>
                        @if($item->restoration_candidate_id)
                            <a target="_blank" href="{{ route('intake.items.preview', [$session->session_id, $item->id, 'suggested']) }}" class="relative"><img src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'suggested']) }}" alt="Suggested edit" class="h-72 w-full object-contain"><span class="absolute left-2 top-2 rounded bg-emerald-950/90 px-2 py-1 text-xs font-semibold text-emerald-100">Suggested</span></a>
                        @else
                            <div class="grid place-items-center p-6 text-center text-sm text-zinc-500">No suggested edit is available. Choose original, hold or reject.</div>
                        @endif
                    </div>
                    <div class="flex flex-wrap justify-between gap-3 px-4 py-3 text-xs text-zinc-400"><span>Position {{ number_format($item->position) }}</span><span>Original retained · edit remains reversible</span></div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-700 p-10 text-center text-zinc-400">No items match this filter. Prepare the next preview checkpoint or choose another filter.</div>
            @endforelse
        </section>

        @if($items->count() > 0)
            <div id="decision-bar" class="sticky bottom-4 z-20 hidden items-center justify-between gap-3 rounded-2xl border border-zinc-600 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur"><p class="text-sm text-zinc-300">Apply one decision to every checked item</p><div class="flex flex-wrap gap-2"><button name="decision" value="suggested_edit" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Use suggested edits</button><button name="decision" value="original" class="rounded-xl border border-zinc-600 px-4 py-2 text-sm font-semibold text-white">Use originals</button><button name="decision" value="hold" class="rounded-xl border border-amber-700 px-4 py-2 text-sm font-semibold text-amber-200">Hold</button><button name="decision" value="reject" class="rounded-xl border border-red-800 px-4 py-2 text-sm font-semibold text-red-200">Reject</button></div></div>
        @endif
    </form>

    <form method="POST" action="{{ route('intake.batches.review', $session->session_id) }}" class="pb-40 sm:hidden">
        @csrf @method('PATCH')
        @if($items->count() > 0)
            <input id="mobile-current-item" type="hidden" name="items[]" value="{{ $items->first()->id }}">
            <div class="mb-3 flex items-center justify-between gap-3">
                <button id="mobile-previous" type="button" class="rounded-xl border border-zinc-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">← Previous</button>
                <p id="mobile-progress" class="text-sm font-semibold text-zinc-300">Photo 1 of {{ $items->count() }}</p>
                <button id="mobile-next" type="button" class="rounded-xl border border-zinc-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Next →</button>
            </div>
        @endif

        <section>
            @forelse($items as $item)
                <article data-mobile-review-card data-item-id="{{ $item->id }}" data-reviewed="{{ $item->review_decision ? '1' : '0' }}" class="overflow-hidden rounded-2xl border {{ $item->attention_code ? 'border-amber-700' : 'border-zinc-700' }} bg-zinc-900 {{ $loop->first ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-700 px-4 py-3">
                        <div class="min-w-0"><p class="truncate font-semibold text-white">{{ $item->original_name }}</p><p class="mt-1 text-xs text-zinc-500">Position {{ number_format($item->position) }}</p></div>
                        <div class="flex shrink-0 gap-2">@if($item->attention_code)<span class="rounded-full bg-amber-950 px-2 py-1 text-xs font-semibold text-amber-200">{{ str($item->attention_code)->replace('_', ' ') }}</span>@endif @if($item->review_decision)<span class="rounded-full bg-emerald-950 px-2 py-1 text-xs font-semibold text-emerald-200">{{ str($item->review_decision)->replace('_', ' ') }}</span>@endif</div>
                    </div>

                    <div class="grid grid-cols-2 gap-1 bg-zinc-950 p-1" role="group" aria-label="Image version">
                        <button type="button" data-mobile-view="original" aria-pressed="true" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-300 aria-pressed:bg-emerald-500 aria-pressed:text-zinc-950">Original</button>
                        <button type="button" data-mobile-view="suggested" aria-pressed="false" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-300 aria-pressed:bg-emerald-500 aria-pressed:text-zinc-950 disabled:opacity-40" {{ $item->restoration_candidate_id ? '' : 'disabled' }}>Suggested</button>
                    </div>

                    <a data-mobile-preview="original" target="_blank" href="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" class="block bg-zinc-950"><img src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" alt="Retained original" class="aspect-[4/3] w-full object-contain"></a>
                    @if($item->restoration_candidate_id)
                        <a data-mobile-preview="suggested" target="_blank" href="{{ route('intake.items.preview', [$session->session_id, $item->id, 'suggested']) }}" class="hidden bg-zinc-950"><img src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'suggested']) }}" alt="Suggested edit" class="aspect-[4/3] w-full object-contain"></a>
                    @endif

                    <div class="px-4 py-3 text-xs text-zinc-400">Original retained · edit remains reversible</div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-700 p-8 text-center text-zinc-400">No items match this filter. Prepare the next preview checkpoint or choose another filter.</div>
            @endforelse
        </section>

        @if($items->count() > 0)
            <div id="mobile-reviewed-state" class="fixed inset-x-2 bottom-2 z-20 hidden rounded-2xl border border-emerald-800 bg-emerald-950/95 p-4 text-center text-sm font-semibold text-emerald-100 shadow-2xl backdrop-blur">This photo has already been reviewed.</div>
            <div id="mobile-decision-bar" class="fixed inset-x-2 bottom-2 z-20 rounded-2xl border border-zinc-600 bg-zinc-950/95 p-3 shadow-2xl backdrop-blur">
                <p class="mb-2 text-xs text-zinc-300">Decision for the current photo</p>
                <div class="grid grid-cols-2 gap-2"><button name="decision" value="suggested_edit" class="rounded-xl bg-emerald-500 px-3 py-3 text-xs font-semibold text-zinc-950">Use suggested edit</button><button name="decision" value="original" class="rounded-xl border border-zinc-600 px-3 py-3 text-xs font-semibold text-white">Use original</button><button name="decision" value="hold" class="rounded-xl border border-amber-700 px-3 py-3 text-xs font-semibold text-amber-200">Hold</button><button name="decision" value="reject" class="rounded-xl border border-red-800 px-3 py-3 text-xs font-semibold text-red-200">Reject</button></div>
            </div>
        @endif
    </form>

    <div>{{ $items->links() }}</div>
</main>
<script>
    const batchItems = [...document.querySelectorAll('.batch-item:not(:disabled)')];
    const decisionBar = document.getElementById('decision-bar');
    const selectVisible = document.getElementById('select-visible');
    const syncDecisionBar = () => {
        const hasSelection = batchItems.some(item => item.checked);
        decisionBar?.classList.toggle('hidden', !hasSelection);
        decisionBar?.classList.toggle('sm:flex', hasSelection);
    };

    selectVisible?.addEventListener('change', event => {
        batchItems.forEach(box => box.checked = event.target.checked);
        syncDecisionBar();
    });
    batchItems.forEach(item => item.addEventListener('change', syncDecisionBar));
    syncDecisionBar();

    const mobileCards = [...document.querySelectorAll('[data-mobile-review-card]')];
    const mobileCurrentItem = document.getElementById('mobile-current-item');
    const mobileProgress = document.getElementById('mobile-progress');
    const mobilePrevious = document.getElementById('mobile-previous');
    const mobileNext = document.getElementById('mobile-next');
    const mobileDecisionBar = document.getElementById('mobile-decision-bar');
    const mobileReviewedState = document.getElementById('mobile-reviewed-state');
    let mobileIndex = 0;

    const showMobileCard = index => {
        if (!mobileCards.length) return;
        mobileIndex = Math.max(0, Math.min(index, mobileCards.length - 1));
        mobileCards.forEach((card, cardIndex) => card.classList.toggle('hidden', cardIndex !== mobileIndex));
        const currentCard = mobileCards[mobileIndex];
        const isReviewed = currentCard.dataset.reviewed === '1';

        mobileCurrentItem.value = currentCard.dataset.itemId;
        mobileCurrentItem.disabled = isReviewed;
        mobileProgress.textContent = `Photo ${mobileIndex + 1} of ${mobileCards.length}`;
        mobilePrevious.disabled = mobileIndex === 0;
        mobileNext.disabled = mobileIndex === mobileCards.length - 1;
        mobileDecisionBar.classList.toggle('hidden', isReviewed);
        mobileReviewedState.classList.toggle('hidden', !isReviewed);
    };

    mobilePrevious?.addEventListener('click', () => showMobileCard(mobileIndex - 1));
    mobileNext?.addEventListener('click', () => showMobileCard(mobileIndex + 1));
    mobileCards.forEach(card => {
        const viewButtons = [...card.querySelectorAll('[data-mobile-view]')];
        const previews = [...card.querySelectorAll('[data-mobile-preview]')];

        viewButtons.forEach(button => button.addEventListener('click', () => {
            const selectedView = button.dataset.mobileView;
            viewButtons.forEach(candidate => candidate.setAttribute('aria-pressed', candidate === button ? 'true' : 'false'));
            previews.forEach(preview => preview.classList.toggle('hidden', preview.dataset.mobilePreview !== selectedView));
        }));
    });
    showMobileCard(0);
</script>
</x-layouts::app>
