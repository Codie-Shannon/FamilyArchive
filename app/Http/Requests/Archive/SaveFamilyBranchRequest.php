<?php

namespace App\Http\Requests\Archive;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveFamilyBranchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_sensitive' => ['required', 'boolean'],
            'review_state' => ['required', Rule::enum(KnowledgeReviewState::class)],
            'confidence' => ['required', Rule::enum(StructuredDateConfidence::class)],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'review_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function branchInput(): array
    {
        return [
            ...$this->safe()->except('expected_metadata_revision'),
            'is_sensitive' => $this->boolean('is_sensitive'),
        ];
    }
}
