<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeatingTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:60'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'zone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
