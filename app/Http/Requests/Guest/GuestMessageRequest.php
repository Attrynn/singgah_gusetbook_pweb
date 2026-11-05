<?php

namespace App\Http\Requests\Guest;

use App\Enums\MessageVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:3', 'max:1000'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'visibility' => ['required', Rule::enum(MessageVisibility::class)],
        ];
    }
}
