@php
    $layout = auth()->user()?->account_state === 'approved'
        ? 'layouts::app'
        : 'layouts.public-discovery';
@endphp

<x-dynamic-component :component="$layout" title="Archive Map">
    <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-6">
        <x-archive-navigation />

        <header class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Privacy-safe geography</p>
                <h1 class="mt-3 text-4xl font-semibold text-white">Archive map</h1>
                <p class="mt-3 max-w-3xl text-lg text-zinc-400">Public photo blips use reviewed, deliberately reduced location precision. Exact archive coordinates are rejected.</p>
            </div>
            <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-200">
                {{ $points->count() }} approved public {{ Str::plural('location', $points->count()) }}
            </div>
        </header>

        <section class="grid gap-5 lg:grid-cols-[1.5fr_0.7fr]">
            <div class="relative min-h-[620px] overflow-hidden rounded-3xl border border-zinc-700 bg-zinc-900">
                <div class="absolute inset-0 opacity-30" style="background-image: linear-gradient(rgba(113,113,122,.25) 1px, transparent 1px), linear-gradient(90deg, rgba(113,113,122,.25) 1px, transparent 1px); background-size: 48px 48px;"></div>
                <div class="absolute inset-10 rounded-[45%_55%_50%_50%] border border-emerald-900/60 bg-emerald-950/20"></div>
                @foreach($points as $point)
                    <div class="absolute -translate-x-1/2 -translate-y-1/2" style="left: {{ $point->map_x }}%; top: {{ $point->map_y }}%;">
                        <span class="absolute -inset-3 animate-pulse rounded-full bg-emerald-400/20"></span>
                        <span class="relative block size-4 rounded-full border-2 border-zinc-950 bg-emerald-300 shadow-[0_0_18px_rgba(110,231,183,.65)]"></span>
                    </div>
                @endforeach
                <div class="absolute bottom-6 left-6 rounded-xl border border-zinc-700 bg-zinc-950/85 px-4 py-3 text-sm text-zinc-400">
                    Illustrative public map · precision intentionally reduced
                </div>
            </div>

            <aside class="space-y-3">
                <h2 class="text-lg font-semibold text-white">Reviewed photo blips</h2>
                @forelse($points as $point)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-white">{{ $point->public_title }}</p>
                            <span class="text-xs uppercase tracking-wide text-emerald-300">{{ $point->precision }}</span>
                        </div>
                        <p class="mt-2 text-sm text-zinc-400">{{ $point->public_place_name }}</p>
                        <p class="mt-3 text-xs text-zinc-600">Privacy reviewed · exact location withheld</p>
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-zinc-700 p-4 text-zinc-400">No approved map points.</p>
                @endforelse
            </aside>
        </section>

        <aside class="rounded-2xl border border-amber-900 bg-amber-950/20 p-5 text-amber-100">
            The public map never exposes private archive coordinates, source records or unreviewed locations.
        </aside>
    </main>
</x-dynamic-component>
