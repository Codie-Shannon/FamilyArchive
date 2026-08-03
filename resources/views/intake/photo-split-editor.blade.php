<x-layouts::app title="Split photos">
<main class="mx-auto w-full max-w-[1500px] space-y-6 p-4 md:p-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('intake.batches.show', [$session->session_id, 'filter' => 'pending']) }}" class="text-sm font-semibold text-emerald-300">← Back to batch review</a>
            <p class="mt-4 text-sm font-semibold text-zinc-400">{{ $item->original_name }}</p>
            <h1 class="mt-1 text-3xl font-semibold text-white">Separate photos from one source</h1>
            <p class="mt-2 max-w-3xl text-zinc-400">Adjust the boxes around each distinct photo. Borders are helpful but not required, and every detected box can be moved, resized, excluded or replaced.</p>
        </div>
        <span class="w-fit rounded-full border border-amber-700 bg-amber-950/40 px-4 py-2 text-sm font-semibold text-amber-100">{{ number_format($proposal->confidence * 100) }}% layout confidence</span>
    </header>

    @if(session('status'))<div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-800 bg-red-950/30 px-4 py-3 text-sm text-red-100">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border border-emerald-800 bg-emerald-950/20 p-4 text-sm text-emerald-100">
        <strong>Original preserved.</strong> These boxes create reversible child candidates. The source file, hash and storage object are never cropped or overwritten.
    </section>

    <section class="rounded-2xl border border-sky-800 bg-sky-950/20 p-4 text-sm text-sky-100">
        <strong>Clipping-safe processing order.</strong> Each selected photo is first copied onto its own padded canvas, then independently rotated or deskewed, and only then edge-cropped for its final preview. A rotation can never be applied to an already-tight crop.
    </section>

    <form id="split-form" method="POST" action="{{ route('intake.items.split.update', [$session->session_id, $item->id]) }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        @csrf
        <section class="space-y-4 rounded-2xl border border-zinc-700 bg-zinc-900 p-3 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="font-semibold text-white">Original source</h2><p class="text-xs text-zinc-400">Drag a box to move it. Drag its lower-right handle to resize it.</p></div>
                <button id="add-region" type="button" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">+ Add photo region</button>
            </div>
            <div id="split-stage" class="relative mx-auto max-h-[72vh] w-fit touch-none select-none overflow-hidden rounded-xl bg-zinc-950">
                <img id="source-image" src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" alt="Immutable multi-photo source" class="block max-h-[72vh] max-w-full object-contain">
                <div id="region-layer" class="absolute inset-0"></div>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
                <div class="flex items-center justify-between gap-3"><h2 class="font-semibold text-white">Photo regions</h2><span id="region-count" class="text-xs font-semibold text-zinc-400"></span></div>
                <div id="region-list" class="mt-4 space-y-3"></div>
            </section>
            <input id="regions-json" type="hidden" name="regions_json">
            <button type="submit" class="w-full rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950">Save split previews</button>
            @if($proposal->state === 'ready')
                <div class="rounded-xl border border-emerald-800 bg-emerald-950/20 p-4 text-sm text-emerald-100">Ready to publish. Return to batch review, select this source and choose <strong>Use split photos</strong>.</div>
            @else
                <p class="text-sm text-zinc-400">Save once the boxes match the distinct photos. You will review the rendered results before publishing them.</p>
            @endif
        </aside>
    </form>

    @if($proposal->regions->contains(fn ($region) => $region->candidate_version_id !== null))
        <section class="space-y-4">
            <div><h2 class="text-xl font-semibold text-white">Rendered split previews</h2><p class="mt-1 text-sm text-zinc-400">Only included previews will become independently browsable archive photos.</p></div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($proposal->regions as $region)
                    @if($region->candidate_version_id)
                        <article class="overflow-hidden rounded-2xl border {{ $region->review_state === 'included' ? 'border-emerald-800' : 'border-zinc-700 opacity-60' }} bg-zinc-900">
                            <img src="{{ route('intake.items.split.preview', [$session->session_id, $item->id, $region->region_id]) }}" alt="Split photo {{ $region->position }}" class="aspect-[4/3] w-full bg-zinc-950 object-contain">
                            <div class="space-y-1 p-3">
                                <p class="text-sm font-semibold text-white">Photo {{ $region->position }} · {{ ucfirst($region->review_state) }}</p>
                                <p class="text-xs text-zinc-400">Padded → {{ $region->rotation_degrees }}° rotation/deskew → final crop</p>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</main>

<script>
    const initialRegions = @js($proposal->regions->map(fn ($region) => [
        'region_id' => $region->region_id,
        'x' => $region->x_basis_points,
        'y' => $region->y_basis_points,
        'width' => $region->width_basis_points,
        'height' => $region->height_basis_points,
        'rotation_degrees' => $region->rotation_degrees,
        'included' => $region->review_state === 'included',
    ])->values());
    let regions = initialRegions.map(region => ({ ...region }));
    const layer = document.getElementById('region-layer');
    const list = document.getElementById('region-list');
    const count = document.getElementById('region-count');
    const payload = document.getElementById('regions-json');
    const stage = document.getElementById('split-stage');
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const regionNumber = index => index + 1;

    const syncPayload = () => {
        payload.value = JSON.stringify(regions.map(region => ({
            region_id: region.region_id || undefined,
            x: Math.round(region.x), y: Math.round(region.y),
            width: Math.round(region.width), height: Math.round(region.height),
            rotation_degrees: Number(region.rotation_degrees || 0),
            included: Boolean(region.included),
        })));
    };

    const beginPointer = (event, index, resize) => {
        event.preventDefault();
        const region = regions[index];
        const bounds = stage.getBoundingClientRect();
        const start = { x: event.clientX, y: event.clientY, region: { ...region } };
        const move = moveEvent => {
            const dx = (moveEvent.clientX - start.x) / bounds.width * 10000;
            const dy = (moveEvent.clientY - start.y) / bounds.height * 10000;
            if (resize) {
                region.width = clamp(start.region.width + dx, 250, 10000 - region.x);
                region.height = clamp(start.region.height + dy, 250, 10000 - region.y);
            } else {
                region.x = clamp(start.region.x + dx, 0, 10000 - region.width);
                region.y = clamp(start.region.y + dy, 0, 10000 - region.height);
            }
            render();
        };
        const end = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', end);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', end);
    };

    const render = () => {
        layer.innerHTML = '';
        list.innerHTML = '';
        regions.forEach((region, index) => {
            const box = document.createElement('div');
            box.className = `absolute cursor-move border-2 ${region.included ? 'border-emerald-400 bg-emerald-400/10' : 'border-zinc-400 bg-zinc-800/30 opacity-70'}`;
            box.style.cssText = `left:${region.x / 100}%;top:${region.y / 100}%;width:${region.width / 100}%;height:${region.height / 100}%`;
            box.innerHTML = `<span class="absolute left-1 top-1 rounded bg-zinc-950/85 px-2 py-1 text-xs font-bold text-white">${regionNumber(index)}</span><button type="button" aria-label="Resize photo ${regionNumber(index)}" class="absolute bottom-0 right-0 size-7 cursor-se-resize rounded-tl bg-emerald-400 text-zinc-950">↘</button>`;
            box.addEventListener('pointerdown', event => beginPointer(event, index, false));
            box.querySelector('button').addEventListener('pointerdown', event => {
                event.stopPropagation();
                beginPointer(event, index, true);
            });
            layer.appendChild(box);

            const row = document.createElement('div');
            row.className = 'rounded-xl border border-zinc-700 bg-zinc-950 p-3';
            row.innerHTML = `<div class="flex items-center justify-between gap-3"><label class="flex items-center gap-2 text-sm font-semibold text-white"><input type="checkbox" ${region.included ? 'checked' : ''} class="size-4 rounded border-zinc-600 bg-zinc-900 text-emerald-500"> Photo ${regionNumber(index)}</label><button type="button" data-action="remove" class="text-xs font-semibold text-red-300">Remove</button></div><p class="mt-2 text-xs text-zinc-500">${Math.round(region.width / 100)}% × ${Math.round(region.height / 100)}% of source · ${Number(region.rotation_degrees || 0)}° clockwise</p><div class="mt-3 grid grid-cols-2 gap-2"><button type="button" data-action="rotate-left" class="rounded-lg border border-zinc-700 px-2 py-2 text-xs font-semibold text-zinc-200">↶ Rotate left</button><button type="button" data-action="rotate-right" class="rounded-lg border border-zinc-700 px-2 py-2 text-xs font-semibold text-zinc-200">↷ Rotate right</button></div>`;
            row.querySelector('input').addEventListener('change', event => { region.included = event.target.checked; render(); });
            row.querySelector('[data-action="remove"]').addEventListener('click', () => { regions.splice(index, 1); render(); });
            row.querySelector('[data-action="rotate-left"]').addEventListener('click', () => { region.rotation_degrees = (Number(region.rotation_degrees || 0) + 270) % 360; render(); });
            row.querySelector('[data-action="rotate-right"]').addEventListener('click', () => { region.rotation_degrees = (Number(region.rotation_degrees || 0) + 90) % 360; render(); });
            list.appendChild(row);
        });
        count.textContent = `${regions.filter(region => region.included).length} included`;
        syncPayload();
    };

    document.getElementById('add-region').addEventListener('click', () => {
        const offset = Math.min(regions.length * 300, 2500);
        regions.push({ x: 1000 + offset, y: 1000 + offset, width: 4000, height: 4000, rotation_degrees: 0, included: true });
        render();
    });
    document.getElementById('split-form').addEventListener('submit', syncPayload);
    render();
</script>
</x-layouts::app>
