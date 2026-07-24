<?php

namespace App\Http\Requests\Archive;

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\DateReviewState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EditPhotoMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $plainTextRules = [
            'nullable',
            'string',
            'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        ];

        return [
            'expected_metadata_revision' => [
                'required',
                'integer',
                'min:0',
            ],
            'title' => [
                ...$plainTextRules,
                'max:160',
            ],
            'description' => [
                ...$plainTextRules,
                'max:2000',
            ],
            'story' => [
                ...$plainTextRules,
                'max:5000',
            ],
            'date_precision' => [
                'required',
                Rule::enum(DatePrecision::class),
            ],
            'canonical_date' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('date_precision'), [
                    DatePrecision::Exact->value,
                    DatePrecision::Approximate->value,
                ], true)),
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
                Rule::prohibitedIf(fn (): bool => ! in_array($this->input('date_precision'), [
                    DatePrecision::Exact->value,
                    DatePrecision::Approximate->value,
                ], true)),
            ],
            'date_year' => [
                Rule::requiredIf(fn (): bool => $this->input('date_precision') === DatePrecision::YearOnly->value),
                'nullable',
                'integer',
                'between:1000,'.now()->year,
                Rule::prohibitedIf(fn (): bool => $this->input('date_precision') !== DatePrecision::YearOnly->value),
            ],
            'estimated_decade' => [
                Rule::requiredIf(fn (): bool => $this->input('date_precision') === DatePrecision::DecadeOnly->value),
                'nullable',
                'integer',
                'between:1000,'.(intdiv(now()->year, 10) * 10),
                'multiple_of:10',
                Rule::prohibitedIf(fn (): bool => $this->input('date_precision') !== DatePrecision::DecadeOnly->value),
            ],
            'date_confidence' => [
                'required',
                Rule::enum(DateConfidence::class)->only([
                    DateConfidence::Confirmed,
                    DateConfidence::High,
                    DateConfidence::Medium,
                    DateConfidence::Low,
                    DateConfidence::Unknown,
                ]),
            ],
            'date_review_state' => [
                'required',
                Rule::enum(DateReviewState::class),
            ],
            'date_source_note' => [
                Rule::requiredIf(fn (): bool => $this->input('date_precision') !== DatePrecision::Unknown->value),
                ...$plainTextRules,
                'max:2000',
            ],
            'date_reason' => [
                Rule::requiredIf(fn (): bool => $this->input('date_precision') !== DatePrecision::Unknown->value),
                ...$plainTextRules,
                'max:2000',
            ],
            'change_reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
                'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            ],
        ];
    }

    /**
     * @return array{
     *     title: ?string,
     *     description: ?string,
     *     story: ?string,
     *     date_precision: string,
     *     canonical_date: ?string,
     *     date_year: ?int,
     *     estimated_decade: ?int,
     *     date_confidence: string,
     *     date_review_state: string,
     *     date_source_note: ?string,
     *     date_reason: ?string,
     *     change_reason: string,
     *     expected_metadata_revision: int
     * }
     */
    public function normalized(): array
    {
        return [
            'title' => $this->normalize($this->input('title')),
            'description' => $this->normalize(
                $this->input('description')
            ),
            'story' => $this->normalize($this->input('story')),
            'date_precision' => (string) $this->input('date_precision'),
            'canonical_date' => $this->normalize($this->input('canonical_date')),
            'date_year' => $this->filled('date_year') ? (int) $this->input('date_year') : null,
            'estimated_decade' => $this->filled('estimated_decade') ? (int) $this->input('estimated_decade') : null,
            'date_confidence' => (string) $this->input('date_confidence'),
            'date_review_state' => (string) $this->input('date_review_state'),
            'date_source_note' => $this->normalize($this->input('date_source_note')),
            'date_reason' => $this->normalize($this->input('date_reason')),
            'change_reason' => trim(
                (string) $this->input('change_reason')
            ),
            'expected_metadata_revision' => (int) $this->input(
                'expected_metadata_revision'
            ),
        ];
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(
            ["\r\n", "\r"],
            "\n",
            trim((string) $value)
        );

        return $normalized === '' ? null : $normalized;
    }
}
