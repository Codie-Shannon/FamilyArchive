<x-layouts::app title="Create Family Album">
    <x-archive-shell>
        <header>
            <a href="{{ route('archive.albums.index') }}" class="text-sm font-semibold text-emerald-300">← All albums</a>
            <h1 class="mt-4 text-3xl font-semibold text-white">Create a family album</h1>
            <p class="mt-2 max-w-2xl text-zinc-400">Give a set of approved archive photos a friendly shared home. You can add photos after creating it.</p>
        </header>
        <form method="POST" action="{{ route('archive.albums.store') }}" class="max-w-3xl space-y-5 rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            <label class="block">
                <span class="text-sm font-semibold text-zinc-200">Album title</span>
                <input name="name" value="{{ old('name') }}" required maxlength="160" placeholder="Summer holidays at the lake" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white">
                @error('name')<span class="mt-1 block text-sm text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-zinc-200">Description <span class="font-normal text-zinc-500">(optional)</span></span>
                <textarea name="description" rows="5" maxlength="2000" placeholder="What connects these photos?" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white">{{ old('description') }}</textarea>
                @error('description')<span class="mt-1 block text-sm text-red-300">{{ $message }}</span>@enderror
            </label>
            <button class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950">Create album</button>
        </form>
    </x-archive-shell>
</x-layouts::app>
