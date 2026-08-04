<x-layouts::app :title="$session->title">
<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-7 p-4 md:p-8">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><a href="{{ route('contributor.index') }}" class="text-sm text-emerald-300">← Contributor intake</a><h1 class="mt-3 text-3xl font-semibold text-white">{{ $session->title }}</h1><p class="mt-2 text-zinc-400">{{ $session->received_files }} of {{ $session->expected_files }} photos retained · {{ $session->status }}</p></div>
        @if($session->status === 'complete' && auth()->user()->canManageTrustedIntake() && $reviewBatchAvailable)
            <a href="{{ route('intake.batches.show', $session->session_id) }}" class="rounded-xl bg-emerald-500 px-5 py-3 text-center text-sm font-semibold text-zinc-950">Review this batch →</a>
        @elseif($session->status === 'complete' && auth()->user()->canManageTrustedIntake())
            <span class="rounded-xl border border-zinc-700 bg-zinc-900 px-5 py-3 text-center text-sm text-zinc-400">Review record unavailable</span>
        @endif
    </header>
    @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
    @if($session->status !== 'complete')
        <form method="POST" enctype="multipart/form-data" action="{{ route('contributor.sessions.upload',$session) }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">@csrf<h2 class="text-xl font-semibold text-white">Add the next photos</h2><p class="mt-1 text-sm text-zinc-400">Select up to 25 at a time. The whole session becomes one review batch.</p><input name="photos[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.tif,.tiff" required class="mt-4 block w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"><button class="mt-4 rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Retain selected photos</button></form>
    @endif
    @if($session->received_files > 0 && $session->status !== 'complete')
        <form method="POST" action="{{ route('contributor.sessions.finish', $session) }}" class="rounded-2xl border border-amber-800 bg-amber-950/20 p-5">@csrf<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-white">Finished adding photos?</h2><p class="mt-1 text-sm text-amber-100">Close this batch at {{ $session->received_files }} photos and send it to review now.</p></div><button class="rounded-lg border border-amber-600 px-5 py-3 font-semibold text-amber-100">Finish batch</button></div></form>
    @endif
    <section class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Processing approach</h2><dl class="mt-4 grid grid-cols-2 gap-3 text-sm">@foreach($session->automation_preferences ?? [] as $key=>$value)<div class="rounded-lg bg-zinc-950 p-3"><dt class="text-zinc-500">{{ str_replace('_',' ',$key) }}</dt><dd class="mt-1 text-white">{{ is_bool($value) ? ($value?'enabled':'disabled') : $value }}</dd></div>@endforeach</dl></div>
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Review status</h2><div class="mt-4 space-y-3">@forelse($session->submissions as $submission)<div class="rounded-lg bg-zinc-950 p-3"><div class="flex justify-between gap-3"><strong class="truncate text-white">{{ $submission->original_name }}</strong><span class="text-emerald-300">{{ str_replace('_',' ',$submission->status) }}</span></div><p class="mt-1 text-xs text-zinc-500">Source retained {{ $submission->incomingUpload?->source_file_retained ? 'yes' : 'no' }}</p>@if($submission->reviewer_note)<p class="mt-2 text-sm text-zinc-300">{{ $submission->reviewer_note }}</p>@endif</div>@empty<p class="text-zinc-500">No photos retained yet.</p>@endforelse</div></div>
    </section>
</div>
</x-layouts::app>
