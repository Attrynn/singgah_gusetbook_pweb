<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckinScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:500'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ];
    }
}
