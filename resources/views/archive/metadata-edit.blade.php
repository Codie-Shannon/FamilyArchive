<x-layouts::app :title="'Edit metadata - '.$mediaItem->archive_id">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-8">
        <header>
            <a href="{{ route('archive.photos.show', $mediaItem) }}" class="text-sm text-emerald-300">&larr; Back to photo</a>
            <p class="mt-5 text-xs font-semibold uppercase text-emerald-300">{{ $mediaItem->archive_id }}</p>
            <h1 class="text-3xl font-semibold text-white">Edit approved metadata</h1>
            <p class="text-zinc-400">Current metadata revision: {{ $mediaItem->metadata_revision }}</p>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-700 p-4 text-red-200">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('archive.photos.metadata.update', $mediaItem) }}" class="space-y-6 rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            @method('PATCH')
            <input type="hidden" name="expected_metadata_revision" value="{{ old('expected_metadata_revision', $mediaItem->metadata_revision) }}">

            <section class="space-y-5">
                <h2 class="text-xl font-semibold text-white">Descriptive metadata</h2>
                <label class="block text-zinc-300">Title
                    <input name="title" maxlength="160" value="{{ old('title', $mediaItem->title) }}" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                </label>
                <label class="block text-zinc-300">Description
                    <textarea name="description" maxlength="2000" rows="5" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">{{ old('description', $mediaItem->description) }}</textarea>
                </label>
                <label class="block text-zinc-300">Story
                    <textarea name="story" maxlength="5000" rows="7" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">{{ old('story', $mediaItem->story) }}</textarea>
                </label>
            </section>

            <section class="space-y-5 border-t border-zinc-700 pt-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Structured historical date</h2>
                    <p class="mt-1 text-sm text-zinc-400">Record uncertainty explicitly. Embedded EXIF dates remain suggestions until an Owner reviews them.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block text-zinc-300">Representation
                        <select name="date_precision" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                            @foreach (\App\Domain\Media\Enums\DatePrecision::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('date_precision', $mediaItem->date_precision->value) === $case->value)>{{ str($case->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-zinc-300">Confidence
                        <select name="structured_date_confidence" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                            @foreach (\App\Domain\Media\Enums\StructuredDateConfidence::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('structured_date_confidence', $mediaItem->structured_date_confidence->value) === $case->value)>{{ str($case->value)->title() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-zinc-300">Exact or approximate date
                        <input type="date" name="canonical_date" value="{{ old('canonical_date', $mediaItem->canonical_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                    </label>
                    <label class="block text-zinc-300">Year only
                        <input type="number" name="date_year" min="1000" max="{{ now()->year }}" value="{{ old('date_year', $mediaItem->date_year) }}" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                    </label>
                    <label class="block text-zinc-300">Decade only
                        <input type="number" name="estimated_decade" min="1000" max="{{ intdiv(now()->year, 10) * 10 }}" step="10" value="{{ old('estimated_decade', $mediaItem->estimated_decade) }}" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                    </label>
                    <label class="block text-zinc-300">Review state
                        <select name="date_review_state" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">
                            @foreach (\App\Domain\Media\Enums\DateReviewState::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('date_review_state', $mediaItem->date_review_state->value) === $case->value)>{{ str($case->value)->title() }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="block text-zinc-300">Date source note
                    <textarea name="date_source_note" maxlength="2000" rows="3" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">{{ old('date_source_note', $mediaItem->date_source_note) }}</textarea>
                </label>
                <label class="block text-zinc-300">Date reasoning
                    <textarea name="date_reason" maxlength="2000" rows="3" class="mt-2 w-full rounded-lg bg-zinc-950 p-3">{{ old('date_reason', $mediaItem->date_reason) }}</textarea>
                </label>
            </section>

            <label class="block text-zinc-300">Revision reason
                <textarea name="change_reason" maxlength="500" rows="3" required class="mt-2 w-full rounded-lg bg-zinc-950 p-3">{{ old('change_reason') }}</textarea>
            </label>

            <div class="rounded-xl border border-amber-700 p-4 text-amber-100">
                <strong>Preservation boundary:</strong> this form changes reviewed database facts only. Archive identity, approval, media bytes, versions, hashes and storage facts remain untouched.
            </div>
            <button class="rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Record metadata revision</button>
        </form>
    </div>
</x-layouts::app>
