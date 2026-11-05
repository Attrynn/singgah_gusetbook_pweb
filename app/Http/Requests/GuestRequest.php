<?php

namespace App\Http\Requests;

use App\Enums\GuestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    protected function eventId(): ?int
    {
        return $this->route('guest')?->event_id ?? (int) $this->input('event_id') ?: null;
    }

    public function rules(): array
    {
        $eventId = $this->eventId();

        return [
            'event_id' => [$this->route('guest') ? 'nullable' : 'required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'guest_category_id' => ['nullable', Rule::exists('guest_categories', 'id')->where('event_id', $eventId)],
            'guest_group_id' => ['nullable', Rule::exists('guest_groups', 'id')->where('event_id', $eventId)],
            'status' => ['required', Rule::enum(GuestStatus::class)],
            'max_attendees' => ['required', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
