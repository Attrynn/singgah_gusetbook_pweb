<?php

namespace App\Http\Requests\Workspace;

use App\Models\Event;
use App\Models\GuestPartyMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveGuestMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        if (! $member instanceof GuestPartyMembership) {
            return false;
        }

        return $this->user()?->can('update', $member) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = $event instanceof Event ? $event->id : null;

        return [
            'target_party_id' => [
                'required',
                'integer',
                Rule::exists('guests', 'id')->where('event_id', $eventId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_party_id.required' => 'Party tujuan wajib dipilih.',
            'target_party_id.exists' => 'Party tujuan tidak ditemukan pada event ini.',
        ];
    }
}
