<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        $ownerId = $this->route('owner')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($ownerId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$ownerId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['boolean'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['integer', 'exists:events,id'],
            'role_labels' => ['nullable', 'array'],
            'role_labels.*' => ['nullable', Rule::in(['groom', 'bride', 'partner'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
