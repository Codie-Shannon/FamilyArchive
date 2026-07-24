<x-layouts::app title="Create source">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header>
            <a href="{{ route('archive.sources.index') }}" class="text-sm text-emerald-300">&larr; Back to sources</a>
            <h1 class="mt-4 text-3xl font-semibold text-white">Create source collection</h1>
        </header>
        @if ($errors->any())
            <div class="rounded-xl border border-red-700 p-4 text-red-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <form method="POST" action="{{ route('archive.sources.store') }}" class="space-y-5 rounded-xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            <label class="block text-zinc-300">Type
                <select name="type" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                    @foreach (\App\Domain\Provenance\Enums\SourceCollectionType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ str($case->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-zinc-300">Name<input name="name" required maxlength="160" value="{{ old('name') }}" class="mt-2 w-full rounded-lg bg-zinc-950 p-3"></label>
            <label class="block text-zinc-300">Description<textarea name="description" maxlength="2000" rows="4" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">{{ old('description') }}</textarea></label>
            <label class="block text-zinc-300">Physical reference<input name="physical_reference" maxlength="255" value="{{ old('physical_reference') }}" class="mt-2 w-full rounded-lg bg-zinc-950 p-3"></label>
            <button class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Create stable source record</button>
        </form>
    </div>
</x-layouts::app>
