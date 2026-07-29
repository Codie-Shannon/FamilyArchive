<?php

namespace App\Http\Requests\Archive;

use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveArchiveEventRequest extends FormRequest
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
            'type' => ['required', Rule::enum(EventType::class)],
            'starts_on' => ['nullable', 'date', 'before_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'before_or_equal:today'],
            'date_precision' => ['required', Rule::enum(DatePrecision::class)],
            'date_year' => ['nullable', 'integer', 'min:1', 'max:'.now()->year],
            'estimated_decade' => ['nullable', 'integer', 'min:0', 'max:'.now()->year],
            'date_confidence' => ['required', Rule::enum(StructuredDateConfidence::class)],
            'date_source_note' => ['nullable', 'string', 'max:2000'],
            'archive_location_id' => ['nullable', 'integer', 'exists:archive_locations,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'review_state' => ['required', Rule::enum(KnowledgeReviewState::class)],
            'review_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
