<?php

namespace App\Http\Requests\M9;

use Illuminate\Foundation\Http\FormRequest;

class MemoryModerateRequest extends FormRequest
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
            'action' => ['required', 'in:approve,reject,hide,unhide,reopen'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
