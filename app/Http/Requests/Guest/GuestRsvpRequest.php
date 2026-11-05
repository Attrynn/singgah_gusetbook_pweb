<?php

namespace App\Http\Requests\Guest;

use App\Enums\RsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                RsvpStatus::Confirmed->value,
                RsvpStatus::Declined->value,
                RsvpStatus::Maybe->value,
            ])],
            'attendance_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'companion_names' => ['nullable', 'string', 'max:1000'],
            'dietary_preference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
