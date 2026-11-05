<?php

namespace App\Http\Requests;

use App\Enums\SongStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SongStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SongStatus::class)],
        ];
    }
}
