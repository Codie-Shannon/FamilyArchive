<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A privacy-first, preservation-grade platform for protecting and understanding family history.">

    <title>Family Archive · Preserve the story</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="relative isolate overflow-hidden">
        <div class="absolute inset-x-0 top-0 -z-10 h-[42rem] bg-[radial-gradient(circle_at_20%_10%,rgba(16,185,129,0.18),transparent_36%),radial-gradient(circle_at_85%_20%,rgba(14,116,144,0.14),transparent_32%)]"></div>

        <header class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Family Archive home">
                <span class="grid size-10 place-items-center rounded-xl border border-emerald-700/70 bg-emerald-950/60 text-lg font-semibold text-emerald-200">FA</span>
                <span>
                    <span class="block text-sm font-semibold tracking-wide text-white">Family Archive</span>
                    <span class="block text-xs text-zinc-500">Preservation before convenience</span>
                </span>
            </a>

            <nav class="flex items-center gap-2" aria-label="Primary navigation">
                <a href="{{ route('public-discovery.index') }}" class="hidden rounded-lg px-4 py-2 text-sm text-zinc-300 transition hover:bg-white/5 hover:text-white sm:inline-flex">Discover</a>
                <a href="{{ route('public-discovery.map') }}" class="hidden rounded-lg px-4 py-2 text-sm text-zinc-300 transition hover:bg-white/5 hover:text-white md:inline-flex">Archive map</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-emerald-400 px-4 py-2 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300">Sign in</a>
                @endauth
            </nav>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl gap-14 px-6 pb-24 pt-16 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:pb-32 lg:pt-24">
                <div class="max-w-3xl">
                    <p class="inline-flex rounded-full border border-emerald-800/80 bg-emerald-950/40 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">
                        Privacy-first family preservation
                    </p>
                    <h1 class="mt-7 text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Preserve the story.
                        <span class="block text-emerald-300">Protect the source.</span>
                    </h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-zinc-300">
                        Family Archive protects original media, records where knowledge came from and keeps every important change under human review—so family history can remain trustworthy across generations.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="{{ route('public-discovery.index') }}" class="rounded-xl bg-emerald-400 px-6 py-3 text-sm font-semibold text-zinc-950 transition hover:bg-emerald-300">
                            Explore the public archive
                        </a>
                        <a href="{{ route('public-discovery.map') }}" class="rounded-xl border border-zinc-700 bg-zinc-900/80 px-6 py-3 text-sm font-semibold text-white transition hover:border-zinc-600 hover:bg-zinc-800">
                            View the archive map
                        </a>
                    </div>
                </div>

                <aside class="relative">
                    <div class="absolute -inset-6 -z-10 rounded-[2rem] bg-emerald-500/5 blur-2xl"></div>
                    <div class="rounded-3xl border border-zinc-800 bg-zinc-900/85 p-6 shadow-2xl shadow-black/30 backdrop-blur">
                        <div class="flex items-center justify-between gap-4 border-b border-zinc-800 pb-5">
                            <div>
                                <p class="text-xs uppercase tracking-[0.16em] text-zinc-500">Preservation contract</p>
                                <p class="mt-1 text-xl font-semibold text-white">Originals remain original</p>
                            </div>
                            <span class="rounded-full bg-emerald-950 px-3 py-1 text-xs font-semibold text-emerald-200">Protected</span>
                        </div>
                        <div class="mt-5 space-y-4">
                            @foreach([
                                ['Immutable source media', 'Viewing and restoration versions are stored separately.'],
                                ['Provenance-aware knowledge', 'Dates, places and identities retain their sources and uncertainty.'],
                                ['Controlled family access', 'Invitations, approvals and branch boundaries govern private material.'],
                                ['Human-reviewed intelligence', 'Duplicates, metadata and restoration candidates never decide for people.'],
                            ] as [$title, $copy])
                                <article class="rounded-2xl border border-zinc-800 bg-zinc-950/75 p-4">
                                    <div class="flex gap-3">
                                        <span class="mt-1 size-2 shrink-0 rounded-full bg-emerald-400"></span>
                                        <div>
                                            <h2 class="font-semibold text-white">{{ $title }}</h2>
                                            <p class="mt-1 text-sm leading-6 text-zinc-400">{{ $copy }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </aside>
            </section>

            <section class="border-y border-zinc-800/80 bg-zinc-900/45">
                <div class="mx-auto grid max-w-7xl gap-px px-6 py-16 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
                    @foreach([
                        ['No overwrite', 'Stored originals are never silently replaced.'],
                        ['Private by default', 'Sensitive media stays behind explicit access boundaries.'],
                        ['Review first', 'Consequential archive decisions remain human controlled.'],
                        ['Evidence led', 'Integrity, source history and revisions stay inspectable.'],
                    ] as [$title, $copy])
                        <article class="border-zinc-800 px-5 py-4 sm:border-l">
                            <h2 class="text-lg font-semibold text-white">{{ $title }}</h2>
                            <p class="mt-2 text-sm leading-6 text-zinc-400">{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-24 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-300">Built for the long term</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-4xl">An archive people can trust.</h2>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <p class="text-base leading-7 text-zinc-300">Curate people, branches, events and locations without pretending uncertain history is exact.</p>
                        <p class="text-base leading-7 text-zinc-300">Share selected stories publicly while preserving precise locations, private originals and family-only context.</p>
                        <p class="text-base leading-7 text-zinc-300">Import, verify and restore media through workflows designed to preserve the source and its chain of custody.</p>
                        <p class="text-base leading-7 text-zinc-300">Prepare for backup, recovery and future custodianship without weakening today’s access controls.</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-800 px-6 py-8">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 text-sm text-zinc-500">
                <p>Family Archive · Privacy-first, preservation-grade family history.</p>
                <p>Private by design. Human-reviewed by default.</p>
            </div>
        </footer>
    </div>
</body>
</html>
