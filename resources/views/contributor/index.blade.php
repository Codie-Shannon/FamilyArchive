<x-layouts::app :title="__('Contribute family media')">
<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-8">
    <header><p class="text-sm font-medium text-emerald-300">Preservation-safe contributor intake</p><h1 class="mt-1 text-3xl font-semibold text-white">Start or resume an upload</h1><p class="mt-2 max-w-3xl text-zinc-400">Every source is retained unchanged in quarantine. Automation settings create future suggestions or candidates only; originals are never modified.</p></header>
    <section class="grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
        <form method="POST" action="{{ route('contributor.sessions.start') }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            <h2 class="text-xl font-semibold text-white">New multi-file session</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm text-zinc-300">Session title<input name="title" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
                <label class="text-sm text-zinc-300">Expected files<input name="expected_files" type="number" min="1" max="100" value="4" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
                <label class="sm:col-span-2 text-sm text-zinc-300">Source context<textarea name="source_context" required placeholder="Who held these items, album or box details, and anything the owner should know." class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></textarea></label>
                <label class="text-sm text-zinc-300">Automation mode<select name="automation_mode" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"><option value="suggestions">Suggestions only</option><option value="candidates">Create review candidates</option><option value="off">Off</option></select></label>
                <label class="text-sm text-zinc-300">Crop target<select name="crop_target" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"><option value="photo_edge">Detect photo edge</option><option value="content">Content-aware suggestion</option><option value="none">No crop</option></select></label>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm text-zinc-300 sm:grid-cols-3">
                @foreach(['auto_rotate'=>'Auto-rotate','deskew'=>'Deskew','perspective'=>'Perspective','exposure'=>'Exposure','color'=>'Color','denoise'=>'Denoise','sharpen'=>'Sharpen','cleanup'=>'Cleanup','damage_repair'=>'Damage repair','upscale'=>'Upscaling','quality_warnings'=>'Quality warnings'] as $name=>$label)
                <label class="flex items-center gap-2 rounded-lg border border-zinc-700 p-3"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked(in_array($name,['auto_rotate','deskew','perspective','quality_warnings']))>{{ $label }}</label>
                @endforeach
            </div>
            <button class="mt-5 rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Create resumable session</button>
        </form>
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Your sessions</h2><div class="mt-4 space-y-3">@forelse($sessions as $session)<a href="{{ route('contributor.sessions.show',$session) }}" class="block rounded-xl border border-zinc-700 bg-zinc-950 p-4 hover:border-emerald-700"><div class="flex items-center justify-between"><strong class="text-white">{{ $session->title }}</strong><span class="rounded-full border border-emerald-800 px-2 py-1 text-xs text-emerald-300">{{ $session->status }}</span></div><p class="mt-2 text-sm text-zinc-400">{{ $session->received_files }} / {{ $session->expected_files }} retained · expires {{ $session->expires_at->format('j M') }}</p></a>@empty<p class="text-zinc-500">No upload sessions yet.</p>@endforelse</div></div>
    </section>
</div>
</x-layouts::app>
