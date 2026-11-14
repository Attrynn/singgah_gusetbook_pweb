<?php

namespace App\Http\Requests\M9;

use Illuminate\Foundation\Http\FormRequest;

class MemorySettingsRequest extends FormRequest
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
            'submissions_open' => ['sometimes', 'boolean'],
            'post_event_submissions' => ['sometimes', 'boolean'],
            'submission_opens_at' => ['nullable', 'date'],
            'submission_closes_at' => ['nullable', 'date', 'after_or_equal:submission_opens_at'],
            'auto_approve' => ['sometimes', 'boolean'],
            'public_sharing' => ['sometimes', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
