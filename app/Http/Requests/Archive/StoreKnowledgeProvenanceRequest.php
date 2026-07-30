<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

final class StoreKnowledgeProvenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'expected_metadata_revision' => ['required', 'integer', 'min:0'],
            'source_collection_id' => ['required', 'integer', 'exists:source_collections,id'],
            'scan_batch_id' => ['nullable', 'integer', 'exists:scan_batches,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
