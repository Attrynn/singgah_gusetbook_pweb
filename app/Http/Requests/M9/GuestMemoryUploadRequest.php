<?php

namespace App\Http\Requests\M9;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class GuestMemoryUploadRequest extends FormRequest
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
        $maxFiles = (int) config('memory.guest_max_files', 10);
        $maxKb = (int) config('media.max_kb', 5120);

        return [
            'files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'caption' => ['nullable', 'string', 'max:255'],
            'client_submission_id' => ['nullable', 'uuid'],
            'allow_event_display' => ['sometimes', 'boolean'],
            'allow_public_display' => ['nullable', 'boolean'],
            'accept_terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $files = $this->file('files');

            if (! is_array($files)) {
                return;
            }

            $maxTotalBytes = (int) config('media.max_total_kb', 51200) * 1024;
            $totalBytes = 0;

            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $totalBytes += $file->getSize();
                }
            }

            if ($totalBytes > $maxTotalBytes) {
                $maxMb = round($maxTotalBytes / 1024 / 1024);
                $validator->errors()->add('files', "Total ukuran file yang diunggah tidak boleh melebihi {$maxMb} MB.");
            }
        });
    }
}
