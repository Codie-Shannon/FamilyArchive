@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="dark">
        <title>{{ $title }} · Family Archive</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <header class="border-b border-zinc-800 bg-zinc-950/95">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-5">
                <a href="{{ route('public-discovery.index') }}" class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-400 font-bold text-zinc-950">FA</span>
                    <span>
                        <strong class="block text-white">Family Archive</strong>
                        <span class="text-xs text-zinc-500">Public discovery</span>
                    </span>
                </a>
                <nav aria-label="Public discovery navigation" class="flex items-center gap-2 text-sm">
                    <a href="{{ route('public-discovery.index') }}" class="rounded-lg px-4 py-2 {{ request()->routeIs('public-discovery.index') ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white' }}">Showcase</a>
                    <a href="{{ route('public-discovery.map') }}" class="rounded-lg px-4 py-2 {{ request()->routeIs('public-discovery.map') ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:text-white' }}">Archive map</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-zinc-300">Private sign in</a>
                </nav>
            </div>
        </header>

        {{ $slot }}

        <footer class="mt-14 border-t border-zinc-800">
            <div class="mx-auto flex max-w-7xl flex-wrap justify-between gap-3 px-6 py-7 text-sm text-zinc-500">
                <p>Only explicitly reviewed material is public.</p>
                <p>Exact archive locations and private records remain protected.</p>
            </div>
        </footer>
    </body>
</html>
