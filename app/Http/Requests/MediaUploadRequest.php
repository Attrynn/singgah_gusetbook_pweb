<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('media.max_files', 10);
        $maxKb = (int) config('media.max_kb', 5120);

        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'media_album_id' => [
                'nullable',
                'integer',
                Rule::exists('media_albums', 'id')->where('event_id', $this->input('event_id')),
            ],
            'files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*' => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'caption' => ['nullable', 'string', 'max:255'],
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
