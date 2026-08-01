<x-layouts::app :title="__('Contribute family media')">
<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-8">
    <header><p class="text-sm font-medium text-emerald-300">Preservation-safe contributor intake</p><h1 class="mt-1 text-3xl font-semibold text-white">Start or resume an upload</h1><p class="mt-2 max-w-3xl text-zinc-400">Add a batch, compare automatic suggestions, then finish it in one review flow. Every source is retained unchanged.</p></header>
    <section class="grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
        <form method="POST" action="{{ route('contributor.sessions.start') }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            <h2 class="text-xl font-semibold text-white">New photo batch</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm text-zinc-300">Batch title<input name="title" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
                <label class="text-sm text-zinc-300">Expected photos<input name="expected_files" type="number" min="1" max="100" value="25" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
                <label class="text-sm text-zinc-300 sm:col-span-2">Source context<textarea name="source_context" required placeholder="Who held these items, album or box details, and anything a reviewer should know." class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></textarea></label>
                <label class="text-sm text-zinc-300 sm:col-span-2">Processing approach<select id="automation-preset" name="automation_preset" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"><option value="balanced">Balanced automatic review — recommended</option><option value="conservative">Conservative suggestions</option><option value="originals">Keep originals only</option><option value="custom">Choose individual controls</option></select><span class="mt-2 block text-xs text-zinc-500">Every approach preserves the original unchanged. A reviewer chooses the preferred view before publication.</span></label>
            </div>
            <details id="advanced-automation" class="mt-5 rounded-xl border border-zinc-700 bg-zinc-950 p-4">
                <summary class="cursor-pointer font-semibold text-white">Advanced processing controls</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm text-zinc-300">Automation mode<select name="automation_mode" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-900 p-3"><option value="suggestions">Suggestions only</option><option value="candidates">Create review candidates</option><option value="off">Off</option></select></label>
                    <label class="text-sm text-zinc-300">Crop target<select name="crop_target" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-900 p-3"><option value="photo_edge">Detect photo edge</option><option value="content">Content-aware suggestion</option><option value="none">No crop</option></select></label>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-zinc-300 sm:grid-cols-3">
                    @foreach(['auto_rotate'=>'Auto-rotate','deskew'=>'Deskew','perspective'=>'Perspective','exposure'=>'Exposure','color'=>'Color','denoise'=>'Denoise','sharpen'=>'Sharpen','cleanup'=>'Cleanup','damage_repair'=>'Damage repair','upscale'=>'Upscaling','quality_warnings'=>'Quality warnings'] as $name=>$label)
                        <label class="flex items-center gap-2 rounded-lg border border-zinc-700 p-3"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked(in_array($name,['auto_rotate','deskew','perspective','quality_warnings']))>{{ $label }}</label>
                    @endforeach
                </div>
            </details>
            <button class="mt-5 rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Create photo batch</button>
        </form>
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Your batches</h2><div class="mt-4 space-y-3">@forelse($sessions as $session)<a href="{{ route('contributor.sessions.show',$session) }}" class="block rounded-xl border border-zinc-700 bg-zinc-950 p-4 hover:border-emerald-700"><div class="flex items-center justify-between"><strong class="text-white">{{ $session->title }}</strong><span class="rounded-full border border-emerald-800 px-2 py-1 text-xs text-emerald-300">{{ $session->status }}</span></div><p class="mt-2 text-sm text-zinc-400">{{ $session->received_files }} / {{ $session->expected_files }} retained · expires {{ $session->expires_at->format('j M') }}</p></a>@empty<p class="text-zinc-500">No upload batches yet.</p>@endforelse</div></div>
    </section>
</div>
<script>document.getElementById('automation-preset')?.addEventListener('change', event => { if (event.target.value === 'custom') document.getElementById('advanced-automation')?.setAttribute('open', 'open'); });</script>
</x-layouts::app>
