<?php

namespace App\Http\Requests\Archive;

use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEventMediaRequest extends FormRequest
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
            'media_item_id' => ['required', 'integer', 'exists:media_items,id'],
            'confidence' => ['required', Rule::enum(StructuredDateConfidence::class)],
            'source_note' => ['required', 'string', 'min:5', 'max:2000'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
