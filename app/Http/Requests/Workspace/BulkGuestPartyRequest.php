<?php

namespace App\Http\Requests\Workspace;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkGuestPartyRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:archive,category,household'],
            'guest_ids' => ['required', 'array', 'min:1'],
            'guest_ids.*' => [
                'integer',
                Rule::exists('guests', 'id')->where('event_id', $eventId),
            ],
            'guest_category_id' => [
                'required_if:action,category',
                'nullable',
                'integer',
                Rule::exists('guest_categories', 'id')->where('event_id', $eventId),
            ],
            'guest_group_id' => [
                'required_if:action,household',
                'nullable',
                'integer',
                Rule::exists('guest_groups', 'id')->where('event_id', $eventId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Aksi bulk wajib dipilih.',
            'guest_ids.required' => 'Pilih minimal satu invitation party.',
            'guest_category_id.required_if' => 'Kategori wajib dipilih untuk aksi ubah kategori.',
            'guest_group_id.required_if' => 'Household wajib dipilih untuk aksi ubah household.',
        ];
    }
}
