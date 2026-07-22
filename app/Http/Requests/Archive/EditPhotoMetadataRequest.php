<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

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
