<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
