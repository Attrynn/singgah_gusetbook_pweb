<?php

namespace App\Http\Requests;

use App\Enums\RsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RsvpUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([RsvpStatus::Confirmed->value, RsvpStatus::Declined->value, RsvpStatus::Maybe->value])],
            'attendance_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'companion_names' => ['nullable', 'string', 'max:2000'],
            'dietary_preference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
