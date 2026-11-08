<?php

namespace App\Http\Requests\Workspace;

use App\Enums\ScheduleStatus;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventScheduleRequest extends FormRequest
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

        return $this->user()?->can('manageSchedule', $event) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['required', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
            'status' => ['nullable', Rule::enum(ScheduleStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
