<x-layouts::app title="Restoration Automation">
    @php
        $recipeById = $recipes->keyBy('id');
        $operationLabels = [
            'auto_rotate' => 'Auto-rotate',
            'deskew' => 'Deskew',
            'exposure' => 'Exposure',
            'color' => 'Colour balance',
            'denoise' => 'Denoise',
            'sharpen' => 'Sharpen',
            'cleanup' => 'Surface cleanup',
        ];
    @endphp
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Owner-controlled restoration</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Non-destructive restoration automation</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">
                    Uploader choices become review candidates. Every operation reads a verified preferred original,
                    writes a separate WebP candidate and waits for a human decision.
                </p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-700 bg-emerald-950/30 px-5 py-4 text-emerald-100">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-700 bg-red-950/30 px-5 py-4 text-red-100">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Immutable sources</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $sources->count() }}</p>
                <p class="mt-1 text-sm text-zinc-400">Ready, preferred originals eligible for analysis.</p>
            </article>
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Review candidates</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $jobs->where('state', 'candidate_ready')->count() }}</p>
                <p class="mt-1 text-sm text-zinc-400">No candidate becomes preferred without approval.</p>
            </article>
            <article class="rounded-xl border border-amber-800 bg-amber-950/20 p-5">
                <p class="text-xs uppercase tracking-wide text-amber-300">External storage</p>
                <p class="mt-2 text-xl font-semibold text-amber-100">SG14 boundary</p>
                <p class="mt-1 text-sm text-amber-200/80">Wasabi remains fail-closed; no live connection is claimed here.</p>
            </article>
        </section>

        <section class="grid gap-6 {{ $focusedCandidateId ? '' : 'xl:grid-cols-[0.9fr_1.4fr]' }}">
            @if(!$focusedCandidateId)
            <form method="POST" action="{{ route('admin.restoration.jobs.queue') }}" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                @csrf
                <div>
                    <p class="text-xs uppercase tracking-wide text-emerald-300">New candidate</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Uploader-controlled recipe</h2>
                    <p class="mt-1 text-sm text-zinc-400">These switches are stored with the job and enforced by the processor.</p>
                </div>
                <label class="mt-5 block text-sm text-zinc-300">Immutable source
                    <select name="source_version_id" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white">
                        @forelse($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->mediaItem?->archive_id }} · {{ $source->mediaItem?->title ?: 'Untitled family photo' }}</option>
                        @empty
                            <option value="">No eligible sources</option>
                        @endforelse
                    </select>
                </label>
                <label class="mt-4 block text-sm text-zinc-300">Recipe name
                    <input name="recipe_name" value="Gentle photographed-print cleanup" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white">
                </label>
                <input type="hidden" name="automation_mode" value="candidates">
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach($operationLabels as $name => $label)
                        <label class="flex items-center gap-3 rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-sm text-zinc-200">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked(in_array($name, ['auto_rotate', 'deskew', 'exposure', 'color'], true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <label class="mt-4 block text-sm text-zinc-300">Crop automation
                    <select name="crop_target" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white">
                        <option value="photo_edge">Find photographed print edges</option>
                        <option value="content">Crop to detected content</option>
                        <option value="none">Do not crop</option>
                    </select>
                </label>
                @foreach(['perspective', 'damage_repair', 'upscale', 'quality_warnings'] as $name)
                    <input type="hidden" name="{{ $name }}" value="{{ $name === 'quality_warnings' ? 1 : 0 }}">
                @endforeach
                <button class="mt-5 w-full rounded-lg bg-emerald-500 px-4 py-3 font-semibold text-zinc-950 hover:bg-emerald-400" @disabled($sources->isEmpty())>
                    Queue separate candidate
                </button>
            </form>
            @endif

            <section class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Processing and review</p>
                        <h2 class="mt-1 text-xl font-semibold text-white">{{ $focusedCandidateId ? 'Focused candidate' : 'Candidate queue' }}</h2>
                    </div>
                    @if($focusedCandidateId)
                        <a href="{{ route('admin.restoration') }}" class="text-sm font-semibold text-emerald-300">Show all candidates →</a>
                    @endif
                </div>
                @forelse($jobs as $job)
                    @php
                        $recipe = $recipeById->get($job->processing_recipe_id);
                        $preferences = (array) $job->automation_preferences;
                        $candidate = $job->candidate;
                    @endphp
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-emerald-300">{{ $job->mediaItem?->archive_id }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-white">{{ $recipe?->name ?? 'Versioned restoration recipe' }}</h3>
                                <p class="mt-1 text-sm text-zinc-400">Recipe v{{ $recipe?->version ?? 1 }} · immutable source retained</p>
                            </div>
                            <span class="rounded-full border border-sky-800 bg-sky-950/40 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-sky-200">{{ str($job->state)->headline() }}</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($operationLabels as $name => $label)
                                @if($preferences[$name] ?? false)
                                    <span class="rounded-full bg-zinc-800 px-3 py-1 text-xs text-zinc-200">{{ $label }}</span>
                                @endif
                            @endforeach
                            @if(($preferences['crop_target'] ?? 'none') !== 'none')
                                <span class="rounded-full bg-zinc-800 px-3 py-1 text-xs text-zinc-200">Auto-crop: {{ str($preferences['crop_target'])->headline() }}</span>
                            @endif
                        </div>

                        @if($job->state === 'queued')
                            <form method="POST" action="{{ route('admin.restoration.jobs.process', $job) }}" class="mt-4">
                                @csrf
                                <button class="rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-zinc-950">Process verified source</button>
                            </form>
                        @elseif($candidate)
                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <figure class="overflow-hidden rounded-lg border border-zinc-700 bg-zinc-950">
                                    <img src="{{ route('admin.restoration.candidates.preview', [$candidate, 'source']) }}" alt="Fictional immutable source preview" class="aspect-[4/3] w-full object-contain">
                                    <figcaption class="border-t border-zinc-700 px-3 py-2 text-xs text-zinc-400">Verified immutable source</figcaption>
                                </figure>
                                <figure class="overflow-hidden rounded-lg border border-emerald-800 bg-zinc-950">
                                    <img src="{{ route('admin.restoration.candidates.preview', [$candidate, 'candidate']) }}" alt="Fictional restoration candidate preview" class="aspect-[4/3] w-full object-contain">
                                    <figcaption class="border-t border-emerald-800 px-3 py-2 text-xs text-emerald-200">Separate review candidate</figcaption>
                                </figure>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg bg-zinc-950 p-3">
                                    <p class="text-xs text-zinc-500">Crop confidence</p>
                                    <p class="mt-1 font-semibold text-white">{{ data_get($candidate->analysis, 'crop.confidence', 'Not applied') }}</p>
                                </div>
                                <div class="rounded-lg bg-zinc-950 p-3">
                                    <p class="text-xs text-zinc-500">Detected skew</p>
                                    <p class="mt-1 font-semibold text-white">{{ data_get($candidate->analysis, 'deskew.degrees', 0) }}°</p>
                                </div>
                                <div class="rounded-lg bg-zinc-950 p-3">
                                    <p class="text-xs text-zinc-500">Original changed</p>
                                    <p class="mt-1 font-semibold text-emerald-300">No · hash verified</p>
                                </div>
                            </div>
                            @if($candidate->review_state === 'pending')
                                <form method="POST" action="{{ route('admin.restoration.candidates.review', $candidate) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                                    @csrf
                                    @method('PATCH')
                                    <input name="review_note" required value="Edges and orientation reviewed; source remains unchanged." class="rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-sm text-white">
                                    <button name="decision" value="approved" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Approve candidate</button>
                                    <button name="decision" value="rejected" class="rounded-lg border border-red-800 px-4 py-2 text-sm font-semibold text-red-200">Reject</button>
                                </form>
                            @else
                                <p class="mt-4 rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-sm text-zinc-300">
                                    {{ str($candidate->review_state)->headline() }} · {{ $candidate->review_note }}
                                </p>
                            @endif
                        @endif
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-zinc-700 p-6 text-zinc-400">No restoration jobs yet.</p>
                @endforelse
            </section>
        </section>

        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Append-only audit trail</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Processing history</h2>
                </div>
                <p class="text-sm text-zinc-400">No paths, hashes or credentials are rendered.</p>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                @forelse($events as $event)
                    <article class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                        <p class="font-semibold text-white">{{ str($event->event)->headline() }}</p>
                        <p class="mt-1 text-sm text-zinc-400">{{ $event->actor?->name ?? 'System' }} · {{ $event->occurred_at?->format('d M Y H:i') }}</p>
                    </article>
                @empty
                    <p class="text-zinc-400">No processing events recorded.</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            Perspective correction, damage reconstruction and upscaling stay manual-only when requested. SG13 never invents missing image content, overwrites an original or silently promotes a candidate.
        </aside>
    </main>
</x-layouts::app>
