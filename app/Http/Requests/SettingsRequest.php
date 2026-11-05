<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'support_email' => ['required', 'email', 'max:190'],
            'default_timezone' => ['required', 'string', 'max:64'],
            'guestbook_auto_approve' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['guestbook_auto_approve' => $this->boolean('guestbook_auto_approve')]);
    }
}
