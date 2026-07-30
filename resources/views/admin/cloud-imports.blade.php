<x-layouts::app title="Media & Cloud Import">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 07 · Post-v1 B</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Media and cloud import</h1>
                <p class="mt-2 text-zinc-400">Controlled photo, video, audio and document intake from approved cloud selections and exports.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <nav aria-label="Cloud import sections" class="flex flex-wrap gap-3">
            <a href="#provider-readiness" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review provider readiness
            </a>
            <a href="#mixed-media-preflight" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review mixed-media preflight
            </a>
            <a href="#playback-profiles" class="rounded-lg border border-zinc-600 bg-zinc-900 px-4 py-2 text-sm text-white outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">
                Review playback profiles
            </a>
        </nav>

        <section id="provider-readiness" aria-labelledby="provider-readiness-heading" class="space-y-4">
            <div>
                <h2 id="provider-readiness-heading" class="text-xl font-semibold text-white">Provider readiness</h2>
                <p class="mt-1 text-sm text-zinc-400">External providers fail closed until their real requirements are satisfied.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">Google Photos Picker</p>
                    <p class="mt-2 text-xl {{ $readiness['google_photos'] ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $readiness['google_photos'] ? 'Configured' : 'Credentials required' }}
                    </p>
                    <p class="mt-2 text-sm text-zinc-500">Only user-selected Picker items enter preflight.</p>
                </article>
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">Apple Photos</p>
                    <p class="mt-2 text-xl {{ $readiness['apple_photos'] ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $readiness['apple_photos'] ? 'Native connector validated' : str($readiness['apple_mode'])->replace('_', ' ')->title() }}
                    </p>
                    <p class="mt-2 text-sm text-zinc-500">
                        {{ $readiness['apple_photos'] ? 'Validated on supported Apple hardware.' : 'Native connector remains unvalidated.' }}
                    </p>
                </article>
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">Document OCR</p>
                    <p class="mt-2 text-xl text-zinc-300">Excluded</p>
                    <p class="mt-2 text-sm text-zinc-500">Searchable scan text is outside this release.</p>
                </article>
            </div>
        </section>

        <section id="mixed-media-preflight" aria-labelledby="mixed-media-preflight-heading" class="grid gap-5 xl:grid-cols-[0.9fr_1.4fr]">
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 id="mixed-media-preflight-heading" class="text-xl font-semibold text-white">Import sessions</h2>
                <p class="mt-1 text-sm text-zinc-400">Planning never bypasses quarantine or human review.</p>
                <div class="mt-4 space-y-3">
                    @forelse($sessions as $session)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ str($session->provider)->replace('_', ' ')->title() }}</p>
                                <span class="text-xs uppercase tracking-wide text-amber-300">{{ $session->state }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                <p class="text-zinc-400"><strong class="block text-lg text-white">{{ $session->selected_count }}</strong>Selected</p>
                                <p class="text-zinc-400"><strong class="block text-lg text-white">{{ $session->imported_count }}</strong>Imported</p>
                                <p class="text-zinc-400"><strong class="block text-lg text-white">{{ $session->failed_count }}</strong>Failed</p>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No fictional imports planned.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <h2 class="text-xl font-semibold text-white">Mixed-media preflight</h2>
                <p class="mt-1 text-sm text-zinc-400">Selected items remain candidates until the existing archive workflow accepts them.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($items as $item)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ str($item->media_type)->headline() }}</p>
                                <span class="text-xs uppercase tracking-wide text-amber-300">{{ $item->state }}</span>
                            </div>
                            <p class="mt-2 truncate text-sm text-zinc-400">{{ $item->original_name }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400 sm:col-span-2">No fictional media selected.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section id="playback-profiles" aria-labelledby="playback-profiles-heading" class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
            <h2 id="playback-profiles-heading" class="text-xl font-semibold text-white">Playback and preview recipes</h2>
            <p class="mt-1 text-sm text-zinc-400">Versioned profiles reserve controlled derivative recipes without claiming completed media support.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                @forelse($profiles as $profile)
                    <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-white">{{ str($profile->media_type)->headline() }}</p>
                            <span class="text-xs uppercase tracking-wide {{ $profile->is_active ? 'text-emerald-300' : 'text-zinc-400' }}">
                                {{ $profile->is_active ? 'Active' : 'Draft' }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-zinc-400">{{ $profile->name }} · version {{ $profile->version }}</p>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400 md:col-span-3">No media profiles configured.</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-xl border border-amber-800 bg-amber-950/20 p-5 text-amber-100">
            Cloud selections still pass validation, quarantine, duplicate review and acceptance. No provider secret is stored in source or evidence.
        </aside>
    </main>
</x-layouts::app>
