<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    public function rules(): array
    {
        $eventId = $this->route('event')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash', Rule::unique('events', 'slug')->ignore($eventId)],
            'groom_name' => ['required', 'string', 'max:100'],
            'bride_name' => ['required', 'string', 'max:100'],
            'groom_profile' => ['nullable', 'string', 'max:2000'],
            'bride_profile' => ['nullable', 'string', 'max:2000'],
            'couple_description' => ['nullable', 'string', 'max:5000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'venue_name' => ['required', 'string', 'max:150'],
            'venue_address' => ['required', 'string', 'max:2000'],
            'google_maps_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'dress_code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'rsvp_deadline' => ['nullable', 'date'],
            'rsvp_enabled' => ['boolean'],
            'guestbook_enabled' => ['boolean'],
            'gallery_enabled' => ['boolean'],
            'gift_enabled' => ['boolean'],
            'live_wall_enabled' => ['boolean'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.title' => ['nullable', 'string', 'max:150'],
            'schedules.*.description' => ['nullable', 'string', 'max:1000'],
            'schedules.*.start_time' => ['nullable', 'date'],
            'schedules.*.end_time' => ['nullable', 'date'],
            'schedules.*.status' => ['nullable', Rule::in(['planned', 'ongoing', 'done'])],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['integer', 'exists:users,id'],
            'owner_roles' => ['nullable', 'array'],
            'owner_roles.*' => ['nullable', Rule::in(['groom', 'bride', 'partner'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rsvp_enabled' => $this->boolean('rsvp_enabled'),
            'guestbook_enabled' => $this->boolean('guestbook_enabled'),
            'gallery_enabled' => $this->boolean('gallery_enabled'),
            'gift_enabled' => $this->boolean('gift_enabled'),
            'live_wall_enabled' => $this->boolean('live_wall_enabled'),
        ]);
    }
}
