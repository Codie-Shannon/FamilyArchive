<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

final class DestroyPhotoProvenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'expected_metadata_revision' => ['required', 'integer', 'min:0'],
            'change_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
