<?php

namespace App\Http\Requests\Archive;

use App\Domain\Provenance\Enums\SourceCollectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSourceCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SourceCollectionType::class)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'physical_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
