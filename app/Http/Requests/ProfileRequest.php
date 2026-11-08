<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Step-up credential. Only required when the login email changes.
            'current_password' => ['nullable', 'string'],
        ];
    }

    /**
     * Changing the login identity is a sensitive operation: control of the
     * new mailbox is not proof that the account owner requested the change.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->changesEmail()) {
                    return;
                }

                if (! $this->filled('current_password')) {
                    $validator->errors()->add(
                        'current_password',
                        'Konfirmasi password saat ini diperlukan untuk mengubah email.'
                    );

                    return;
                }

                if (! Hash::check((string) $this->input('current_password'), (string) $this->user()->password)) {
                    $validator->errors()->add('current_password', 'Password saat ini tidak sesuai.');
                }
            },
        ];
    }

    public function changesEmail(): bool
    {
        return strtolower(trim((string) $this->input('email'))) !== strtolower((string) $this->user()->email);
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'current_password.string' => 'Password saat ini tidak valid.',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'password saat ini',
        ];
    }
}
