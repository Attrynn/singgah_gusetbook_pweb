<?php

namespace App\Http\Requests\Workspace;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestPartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $event = $this->route('event');

        if (! $organization instanceof Organization || ! $event instanceof Event) {
            return false;
        }

        if ((int) $event->organization_id !== (int) $organization->id) {
            abort(404);
        }

        return $this->user()?->can('manageGuests', $event) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->route('event')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'max_attendees' => ['required', 'integer', 'min:1', 'max:100'],
            'guest_category_id' => [
                'nullable',
                'integer',
                Rule::exists('guest_categories', 'id')->where('event_id', $eventId),
            ],
            'guest_group_id' => [
                'nullable',
                'integer',
                Rule::exists('guest_groups', 'id')->where('event_id', $eventId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:active,inactive,blocked'],
            'members' => ['nullable', 'array'],
            'members.*.name' => ['required_with:members', 'string', 'max:255'],
            'members.*.email' => ['nullable', 'email', 'max:255'],
            'members.*.phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama invitation party wajib diisi.',
            'max_attendees.required' => 'Kuota undangan wajib diisi.',
            'max_attendees.min' => 'Kuota undangan minimal 1 orang.',
            'members.*.name.required_with' => 'Nama individu anggota wajib diisi.',
        ];
    }
}
