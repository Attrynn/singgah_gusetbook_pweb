<?php

namespace App\Http\Requests\M9;

use Illuminate\Foundation\Http\FormRequest;

class GuestMemoryMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sender_name' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:3', 'max:1000'],
            'client_submission_id' => ['nullable', 'uuid'],
            'allow_event_display' => ['sometimes', 'boolean'],
            'allow_public_display' => ['nullable', 'boolean'],
            'accept_terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $message = $this->input('message');

            if (! is_string($message)) {
                return;
            }

            if (preg_match('/(https?:\/\/|www\.)/i', $message) === 1) {
                $validator->errors()->add('message', 'Pesan tidak boleh mengandung tautan atau URL.');
            }

            if (preg_match('/<[^>]*>/', $message) === 1) {
                $validator->errors()->add('message', 'Pesan tidak boleh mengandung tag HTML.');
            }
        });
    }
}
