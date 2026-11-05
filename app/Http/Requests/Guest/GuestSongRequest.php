<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class GuestSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'song_title' => ['required', 'string', 'max:150'],
            'artist' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
