<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:150'],
            'correct_index' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $count = count($this->input('options', []));
            if ((int) $this->input('correct_index') >= $count) {
                $validator->errors()->add('correct_index', 'Jawaban benar tidak sesuai dengan pilihan.');
            }
        });
    }
}
