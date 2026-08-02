@if(session('access_card'))
@php($card = session('access_card'))
<section class="rounded-2xl border-2 border-emerald-500 bg-emerald-950/30 p-5 sm:p-6" data-test="family-access-card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-[.18em] text-emerald-300">{{ $card['purpose'] === 'recovery' ? 'One-time recovery card' : 'Family access card' }}</p><h2 class="mt-1 text-2xl font-semibold text-white">{{ $card['name'] }}</h2></div>
        <button type="button" onclick="window.print()" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-100">Print card</button>
    </div>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Member name</p><p class="mt-2 text-lg font-semibold text-white">{{ $card['username'] ?: 'Existing account' }}</p></div>
        <div class="rounded-xl bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">One-time code</p><p class="mt-2 font-mono text-2xl font-bold tracking-widest text-emerald-300">{{ $card['code'] }}</p></div>
    </div>
    <p class="mt-4 text-sm text-zinc-300">Go to <strong>{{ route('access-code.show') }}</strong> and enter the code. It expires {{ $card['expires_at'] }} and disappears after one use.</p>
</section>
@endif
