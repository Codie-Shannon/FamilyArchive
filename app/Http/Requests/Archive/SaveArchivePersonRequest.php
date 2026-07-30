<?php

namespace App\Http\Requests\Archive;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveArchivePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'expected_metadata_revision' => [
                $this->isMethod('PATCH') ? 'required' : 'nullable',
                'integer',
                'min:0',
            ],
            'display_name' => ['required', 'string', 'max:160'],
            'alternate_names' => ['nullable', 'string', 'max:2000'],
            'name_certainty' => ['required', Rule::enum(PersonNameCertainty::class)],
            'birth_on' => ['nullable', 'date', 'before_or_equal:today'],
            'birth_year' => ['nullable', 'integer', 'min:1', 'max:'.now()->year],
            'birth_decade' => ['nullable', 'integer', 'min:0', 'max:'.now()->year],
            'birth_precision' => ['required', Rule::enum(PersonDatePrecision::class)],
            'death_on' => ['nullable', 'date', 'before_or_equal:today'],
            'death_year' => ['nullable', 'integer', 'min:1', 'max:'.now()->year],
            'death_decade' => ['nullable', 'integer', 'min:0', 'max:'.now()->year],
            'death_precision' => ['required', Rule::enum(PersonDatePrecision::class)],
            'life_state' => ['required', Rule::in(['living', 'deceased', 'unknown'])],
            'fact_confidence' => ['required', Rule::enum(StructuredDateConfidence::class)],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'is_private' => ['required', 'boolean'],
            'family_branch_id' => ['nullable', 'integer', 'exists:family_branches,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'review_state' => ['required', Rule::enum(KnowledgeReviewState::class)],
            'review_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function personInput(): array
    {
        $alternateNames = preg_split(
            '/\r\n|\r|\n/',
            (string) ($this->validated('alternate_names') ?? '')
        );

        return [
            ...$this->safe()->except(['expected_metadata_revision', 'alternate_names']),
            'alternate_names' => $alternateNames === false ? [] : $alternateNames,
            'is_private' => $this->boolean('is_private'),
        ];
    }
}
