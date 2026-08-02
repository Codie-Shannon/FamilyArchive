<x-layouts::app title="Edit original">
<main class="mx-auto w-full max-w-[1700px] space-y-5 p-4 md:p-8">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('intake.batches.show', [$session->session_id, 'filter' => 'pending']) }}" class="text-sm font-semibold text-emerald-300">← Back to batch review</a>
            <p class="mt-4 text-sm font-semibold text-zinc-400">{{ $item->original_name }}</p>
            <h1 class="mt-1 text-3xl font-semibold text-white">Edit original</h1>
            <p class="mt-2 max-w-3xl text-zinc-400">Create your own edit directly from the retained original. Saving adds a new review version; the archival original and every earlier edit remain preserved.</p>
        </div>
        <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">Non-destructive · human approval still required</div>
    </header>

    @if($errors->any())
        <div class="rounded-xl border border-red-800 bg-red-950/30 px-4 py-3 text-sm text-red-100">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('intake.items.editor.update', [$session->session_id, $item->id]) }}" id="manual-editor" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_25rem]">
        @csrf
        <section class="space-y-4">
            <div class="overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-950">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-700 bg-zinc-900 px-4 py-3">
                    <div><p class="font-semibold text-white">Your edit of the original</p><p class="text-xs text-zinc-400">Every adjustment shown here is recreated from the verified original when you save.</p></div>
                    <div class="flex gap-2"><button type="button" id="rotate-left" class="rounded-lg border border-zinc-600 px-3 py-2 text-sm font-semibold text-white">↶ 90°</button><button type="button" id="rotate-right" class="rounded-lg border border-zinc-600 px-3 py-2 text-sm font-semibold text-white">↷ 90°</button><button type="button" id="reset-editor" class="rounded-lg border border-zinc-600 px-3 py-2 text-sm font-semibold text-zinc-300">Reset</button></div>
                </div>
                <div class="grid min-h-[28rem] place-items-center p-3 sm:p-6">
                    <canvas id="editor-canvas" class="max-h-[70vh] max-w-full rounded-lg bg-[#f5f1e5] shadow-2xl" aria-label="Live preview of the manually adjusted image"></canvas>
                </div>
            </div>

            <details class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
                <summary class="cursor-pointer font-semibold text-white">Optional: compare with the automatic version</summary>
                <div class="mt-4 grid gap-3 {{ $candidate ? 'sm:grid-cols-2' : '' }}">
                    <a target="_blank" href="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-950"><img src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" alt="Immutable original" class="aspect-[4/3] w-full object-contain"><span class="block border-t border-zinc-700 px-3 py-2 text-xs font-semibold text-zinc-300">Immutable original</span></a>
                    @if($candidate)
                        <a target="_blank" href="{{ route('intake.items.preview', [$session->session_id, $item->id, 'suggested']) }}" class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-950"><img src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'suggested']) }}" alt="Current automatic suggestion" class="aspect-[4/3] w-full object-contain"><span class="block border-t border-zinc-700 px-3 py-2 text-xs font-semibold text-zinc-300">Current automatic suggestion</span></a>
                    @else
                        <p class="rounded-xl border border-dashed border-zinc-700 p-4 text-sm text-zinc-400">Automation did not create a usable suggestion. You can still make and save your own edit from the original.</p>
                    @endif
                </div>
            </details>
        </section>

        <aside class="space-y-4 xl:max-h-[calc(100vh-3rem)] xl:overflow-y-auto xl:pr-1">
            <fieldset class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
                <legend class="px-1 font-semibold text-white">Orientation & straightening</legend>
                <label class="mt-2 flex items-start gap-3 text-sm text-zinc-200"><input type="hidden" name="orient" value="0"><input type="checkbox" name="orient" value="1" @checked(old('orient', 1)) class="mt-0.5 size-4 rounded border-zinc-600 bg-zinc-950 text-emerald-500">Respect camera orientation metadata</label>
                <input type="hidden" name="quarter_turn" id="quarter-turn" value="{{ old('quarter_turn', 0) }}">
                <label class="mt-5 block text-sm font-medium text-zinc-200">Fine straighten <output data-readout="straighten">{{ old('straighten', 0) }}°</output><input data-editor-control name="straighten" type="range" min="-8" max="8" step="0.1" value="{{ old('straighten', 0) }}" class="mt-2 w-full accent-emerald-500"></label>
            </fieldset>

            <fieldset class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
                <legend class="px-1 font-semibold text-white">Crop framing</legend>
                <p class="mb-4 text-xs text-zinc-400">Trim each edge independently. The preview always shows the retained area.</p>
                @foreach(['crop_left' => 'Left', 'crop_top' => 'Top', 'crop_right' => 'Right', 'crop_bottom' => 'Bottom'] as $name => $label)
                    <label class="mb-4 block text-sm font-medium text-zinc-200">{{ $label }} <output data-readout="{{ $name }}">{{ old($name, 0) }}%</output><input data-editor-control name="{{ $name }}" type="range" min="0" max="45" step="0.5" value="{{ old($name, 0) }}" class="mt-2 w-full accent-emerald-500"></label>
                @endforeach
            </fieldset>

            <fieldset class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
                <legend class="px-1 font-semibold text-white">Exposure & colour</legend>
                @foreach(['brightness' => ['Brightness', -40, 40], 'contrast' => ['Contrast', -30, 30], 'red' => ['Red balance', -20, 20], 'green' => ['Green balance', -20, 20], 'blue' => ['Blue balance', -20, 20]] as $name => [$label, $min, $max])
                    <label class="mb-4 block text-sm font-medium text-zinc-200">{{ $label }} <output data-readout="{{ $name }}">{{ old($name, 0) }}</output><input data-editor-control name="{{ $name }}" type="range" min="{{ $min }}" max="{{ $max }}" step="1" value="{{ old($name, 0) }}" class="mt-2 w-full accent-emerald-500"></label>
                @endforeach
            </fieldset>

            <fieldset class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
                <legend class="px-1 font-semibold text-white">Detail & cleanup</legend>
                @foreach(['denoise' => ['Denoise', 3], 'sharpen' => ['Sharpen', 2], 'cleanup' => ['Surface cleanup', 3]] as $name => [$label, $max])
                    <label class="mb-4 block text-sm font-medium text-zinc-200">{{ $label }} <output data-readout="{{ $name }}">{{ old($name, 0) }}</output><input data-editor-control name="{{ $name }}" type="range" min="0" max="{{ $max }}" step="1" value="{{ old($name, 0) }}" class="mt-2 w-full accent-emerald-500"></label>
                @endforeach
                <p class="text-xs text-zinc-500">Detail filters are rendered at full resolution after crop and rotation. The browser preview is representative; the saved candidate is authoritative.</p>
            </fieldset>

            <div class="sticky bottom-2 rounded-2xl border border-zinc-600 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur">
                <button class="w-full rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950">Save my edited version</button>
                <p class="mt-2 text-center text-xs text-zinc-400">Nothing is published until this edited version is accepted in batch review.</p>
            </div>
        </aside>
    </form>
</main>

<img id="editor-source" src="{{ route('intake.items.preview', [$session->session_id, $item->id, 'original']) }}" alt="" class="hidden">
<script>
    const editor = document.getElementById('manual-editor');
    const source = document.getElementById('editor-source');
    const canvas = document.getElementById('editor-canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const quarterTurn = document.getElementById('quarter-turn');
    const controls = [...editor.querySelectorAll('[data-editor-control]')];
    const numeric = name => Number(editor.elements[name].value || 0);
    let queued = false;

    const rotateCanvas = (input, degrees) => {
        if (!degrees) return input;
        const radians = degrees * Math.PI / 180;
        const sin = Math.abs(Math.sin(radians));
        const cos = Math.abs(Math.cos(radians));
        const output = document.createElement('canvas');
        output.width = Math.max(1, Math.ceil(input.width * cos + input.height * sin));
        output.height = Math.max(1, Math.ceil(input.width * sin + input.height * cos));
        const outputContext = output.getContext('2d');
        outputContext.fillStyle = '#f5f1e5';
        outputContext.fillRect(0, 0, output.width, output.height);
        outputContext.translate(output.width / 2, output.height / 2);
        outputContext.rotate(radians);
        outputContext.drawImage(input, -input.width / 2, -input.height / 2);
        return output;
    };

    const render = () => {
        queued = false;
        if (!source.complete || !source.naturalWidth) return;
        const scale = Math.min(1, 1400 / Math.max(source.naturalWidth, source.naturalHeight));
        let working = document.createElement('canvas');
        working.width = Math.max(1, Math.round(source.naturalWidth * scale));
        working.height = Math.max(1, Math.round(source.naturalHeight * scale));
        working.getContext('2d').drawImage(source, 0, 0, working.width, working.height);

        working = rotateCanvas(working, numeric('quarter_turn') * -90 - numeric('straighten'));
        const left = numeric('crop_left') / 100;
        const top = numeric('crop_top') / 100;
        const right = numeric('crop_right') / 100;
        const bottom = numeric('crop_bottom') / 100;
        const sx = Math.round(working.width * left);
        const sy = Math.round(working.height * top);
        const sw = Math.max(1, Math.round(working.width * (1 - left - right)));
        const sh = Math.max(1, Math.round(working.height * (1 - top - bottom)));

        canvas.width = Math.min(1400, sw);
        canvas.height = Math.max(1, Math.round(sh * canvas.width / sw));
        context.save();
        const brightness = 100 + numeric('brightness');
        const contrast = 100 + numeric('contrast');
        const soften = numeric('denoise') + numeric('cleanup');
        context.filter = `brightness(${brightness}%) contrast(${contrast}%) blur(${soften * 0.18}px)`;
        context.drawImage(working, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
        context.restore();

        const red = numeric('red');
        const green = numeric('green');
        const blue = numeric('blue');
        if (red || green || blue) {
            const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
            for (let index = 0; index < pixels.data.length; index += 4) {
                pixels.data[index] = Math.max(0, Math.min(255, pixels.data[index] + red));
                pixels.data[index + 1] = Math.max(0, Math.min(255, pixels.data[index + 1] + green));
                pixels.data[index + 2] = Math.max(0, Math.min(255, pixels.data[index + 2] + blue));
            }
            context.putImageData(pixels, 0, 0);
        }
    };
    const scheduleRender = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(render);
    };
    const updateReadouts = () => {
        controls.forEach(control => {
            const output = editor.querySelector(`[data-readout="${control.name}"]`);
            if (!output) return;
            output.textContent = control.name.startsWith('crop_') ? `${control.value}%` : (control.name === 'straighten' ? `${control.value}°` : control.value);
        });
    };
    const sync = () => { updateReadouts(); scheduleRender(); };
    controls.forEach(control => control.addEventListener('input', sync));
    document.getElementById('rotate-left').addEventListener('click', () => { quarterTurn.value = Math.max(-2, numeric('quarter_turn') - 1); scheduleRender(); });
    document.getElementById('rotate-right').addEventListener('click', () => { quarterTurn.value = Math.min(2, numeric('quarter_turn') + 1); scheduleRender(); });
    document.getElementById('reset-editor').addEventListener('click', () => {
        editor.reset();
        quarterTurn.value = 0;
        sync();
    });
    source.addEventListener('load', render);
    if (source.complete) render();
    updateReadouts();
</script>
</x-layouts::app>
