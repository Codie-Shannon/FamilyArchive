@php($editing = isset($person))
@php($currentNameCertainty = old('name_certainty', $editing ? $person->name_certainty->value : 'unknown'))
@php($currentBirthPrecision = old('birth_precision', $editing ? $person->birth_precision->value : 'unknown'))
@php($currentDeathPrecision = old('death_precision', $editing ? $person->death_precision->value : 'unknown'))
@php($currentLifeState = old('life_state', $editing ? $person->life_state : 'unknown'))
@php($currentConfidence = old('fact_confidence', $editing ? $person->fact_confidence->value : 'unknown'))
@php($currentReviewState = old('review_state', $editing ? $person->review_state->value : 'accepted'))
@php($currentBranch = old('family_branch_id', $editing ? $person->family_branch_id : null))

<div class="grid gap-5 md:grid-cols-2">
    <label class="space-y-2"><span class="text-sm font-medium">Display name</span><input name="display_name" required maxlength="160" value="{{ old('display_name', $editing ? $person->display_name : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
    <label class="space-y-2"><span class="text-sm font-medium">Name certainty</span><select name="name_certainty" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(\App\Domain\Knowledge\Enums\PersonNameCertainty::cases() as $case)<option value="{{ $case->value }}" @selected($currentNameCertainty === $case->value)>{{ str($case->value)->headline() }}</option>@endforeach</select></label>
    <label class="space-y-2 md:col-span-2"><span class="text-sm font-medium">Alternate names (one per line)</span><textarea name="alternate_names" rows="3" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">{{ old('alternate_names', $editing ? collect($person->alternate_names)->implode("\n") : '') }}</textarea></label>

    <fieldset class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <legend class="px-2 text-sm font-semibold">Birth evidence</legend>
        <label class="space-y-2"><span class="text-sm font-medium">Precision</span><select name="birth_precision" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(\App\Domain\Knowledge\Enums\PersonDatePrecision::cases() as $case)<option value="{{ $case->value }}" @selected($currentBirthPrecision === $case->value)>{{ str($case->value)->headline() }}</option>@endforeach</select></label>
        <label class="space-y-2"><span class="text-sm font-medium">Exact or approximate date</span><input type="date" name="birth_on" value="{{ old('birth_on', $editing ? $person->birth_on?->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="space-y-2"><span class="text-sm font-medium">Year only</span><input type="number" name="birth_year" min="1" max="{{ now()->year }}" value="{{ old('birth_year', $editing ? $person->birth_year : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
            <label class="space-y-2"><span class="text-sm font-medium">Decade only</span><input type="number" name="birth_decade" min="0" step="10" max="{{ now()->year }}" value="{{ old('birth_decade', $editing ? $person->birth_decade : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
        </div>
    </fieldset>

    <fieldset class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <legend class="px-2 text-sm font-semibold">Death evidence</legend>
        <label class="space-y-2"><span class="text-sm font-medium">Precision</span><select name="death_precision" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(\App\Domain\Knowledge\Enums\PersonDatePrecision::cases() as $case)<option value="{{ $case->value }}" @selected($currentDeathPrecision === $case->value)>{{ str($case->value)->headline() }}</option>@endforeach</select></label>
        <label class="space-y-2"><span class="text-sm font-medium">Exact or approximate date</span><input type="date" name="death_on" value="{{ old('death_on', $editing ? $person->death_on?->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="space-y-2"><span class="text-sm font-medium">Year only</span><input type="number" name="death_year" min="1" max="{{ now()->year }}" value="{{ old('death_year', $editing ? $person->death_year : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
            <label class="space-y-2"><span class="text-sm font-medium">Decade only</span><input type="number" name="death_decade" min="0" step="10" max="{{ now()->year }}" value="{{ old('death_decade', $editing ? $person->death_decade : '') }}" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"></label>
        </div>
    </fieldset>

    <label class="space-y-2"><span class="text-sm font-medium">Life state</span><select name="life_state" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(['living', 'deceased', 'unknown'] as $state)<option value="{{ $state }}" @selected($currentLifeState === $state)>{{ str($state)->headline() }}</option>@endforeach</select></label>
    <label class="space-y-2"><span class="text-sm font-medium">Fact confidence</span><select name="fact_confidence" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(\App\Domain\Media\Enums\StructuredDateConfidence::cases() as $case)<option value="{{ $case->value }}" @selected($currentConfidence === $case->value)>{{ str($case->value)->headline() }}</option>@endforeach</select></label>
    <label class="space-y-2"><span class="text-sm font-medium">Reviewed family branch</span><select name="family_branch_id" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="">No reviewed branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $currentBranch === (string) $branch->id)>{{ $branch->branch_id }} · {{ $branch->name }}</option>@endforeach</select></label>
    <label class="space-y-2"><span class="text-sm font-medium">Sensitive record</span><select name="is_private" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950"><option value="0" @selected((string) old('is_private', $editing ? (int) $person->is_private : 0) === '0')>No — browse reviewed facts</option><option value="1" @selected((string) old('is_private', $editing ? (int) $person->is_private : 0) === '1')>Yes — redact browse facts</option></select></label>
    <label class="space-y-2 md:col-span-2"><span class="text-sm font-medium">Source note</span><textarea name="source_note" rows="3" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">{{ old('source_note', $editing ? $person->source_note : '') }}</textarea></label>
    <label class="space-y-2 md:col-span-2"><span class="text-sm font-medium">Reviewed notes</span><textarea name="notes" rows="4" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">{{ old('notes', $editing ? $person->notes : '') }}</textarea></label>
    <label class="space-y-2"><span class="text-sm font-medium">Review state</span><select name="review_state" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">@foreach(\App\Domain\Knowledge\Enums\KnowledgeReviewState::cases() as $case)<option value="{{ $case->value }}" @selected($currentReviewState === $case->value)>{{ str($case->value)->headline() }}</option>@endforeach</select></label>
    <label class="space-y-2 md:col-span-2"><span class="text-sm font-medium">Review reason</span><textarea name="review_reason" required rows="3" class="w-full rounded-lg border border-zinc-300 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">{{ old('review_reason', $editing ? $person->review_reason : '') }}</textarea></label>
</div>

@if($errors->any())
    <div class="mt-5 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
@endif
