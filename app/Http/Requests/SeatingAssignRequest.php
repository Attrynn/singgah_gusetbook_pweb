<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeatingAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'seating_table_id' => ['required', 'integer', 'exists:seating_tables,id'],
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'seat_number' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
