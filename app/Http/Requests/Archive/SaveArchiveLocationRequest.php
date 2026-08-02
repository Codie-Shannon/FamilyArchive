<?php

namespace App\Http\Requests\Archive;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveArchiveLocationRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:160'],
            'locality' => ['nullable', 'string', 'max:160'],
            'precision' => ['required', Rule::enum(LocationPrecision::class)],
            'is_sensitive' => ['required', 'boolean'],
            'review_state' => ['required', Rule::enum(KnowledgeReviewState::class)],
            'confidence' => ['required', Rule::enum(StructuredDateConfidence::class)],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'review_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array{
     *   label: string,
     *   subtitle: ?string,
     *   address: ?string,
     *   country_code: ?string,
     *   region: ?string,
     *   locality: ?string,
     *   precision: string,
     *   is_sensitive: bool,
     *   review_state: string,
     *   confidence: string,
     *   source_note: ?string,
     *   review_reason: string
     * }
     */
    public function locationInput(): array
    {
        return [
            'label' => (string) $this->validated('label'),
            'subtitle' => $this->validated('subtitle'),
            'address' => $this->validated('address'),
            'country_code' => $this->validated('country_code'),
            'region' => $this->validated('region'),
            'locality' => $this->validated('locality'),
            'precision' => (string) $this->validated('precision'),
            'is_sensitive' => $this->boolean('is_sensitive'),
            'review_state' => (string) $this->validated('review_state'),
            'confidence' => (string) $this->validated('confidence'),
            'source_note' => $this->validated('source_note'),
            'review_reason' => (string) $this->validated('review_reason'),
        ];
    }
}
