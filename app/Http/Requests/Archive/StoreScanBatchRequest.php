<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

final class StoreScanBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:160'],
            'scanned_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
