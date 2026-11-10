<?php

namespace App\Http\Requests\Workspace;

use App\Enums\RunOfShowStatus;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRunOfShowItemRequest extends FormRequest
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

        return $this->user()?->can('manageRunOfShow', $event) ?? false;
    }

    /**
     * Accept the DB/service column name (`event_session_id`) while still
     * tolerating the shorter `session_id` used by form payloads.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('session_id') && ! $this->has('event_session_id')) {
            $this->merge(['event_session_id' => $this->input('session_id')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'event_session_id' => ['nullable', 'integer', 'exists:event_sessions,id'],
            'phase_id' => ['nullable', 'integer', 'exists:event_phases,id'],
            'pic_staff_membership_id' => ['nullable', 'integer', 'exists:event_staff_memberships,id'],
            'station_id' => ['nullable', 'integer', 'exists:event_stations,id'],
            'depends_on_item_id' => ['nullable', 'integer', 'exists:event_run_of_show_items,id'],
            'assignee_membership_ids' => ['nullable', 'array'],
            'assignee_membership_ids.*' => ['integer', 'exists:event_staff_memberships,id'],
            'allow_parallel' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::enum(RunOfShowStatus::class)],
            'planned_start_local' => ['nullable', 'date'],
            'planned_end_local' => ['nullable', 'date'],
            'actual_start_local' => ['nullable', 'date'],
            'actual_end_local' => ['nullable', 'date'],
            'internal_description' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
