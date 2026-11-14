<?php

namespace App\Http\Requests\M9;

use Illuminate\Foundation\Http\FormRequest;

class MemoryExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection_id' => ['nullable', 'integer', 'exists:memory_collections,id'],
            'include_photos' => ['sometimes', 'boolean'],
            'include_messages' => ['sometimes', 'boolean'],
        ];
    }
}
