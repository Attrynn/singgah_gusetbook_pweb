<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GiftAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_owner' => ['required', 'string', 'max:150'],
            'qris' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gift_address' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
