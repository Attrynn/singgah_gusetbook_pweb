<?php

namespace App\Http\Requests\Workspace;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class SaveEventSetupRequest extends FormRequest
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

        return $this->user()?->can('manageSetup', $event) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phases' => ['nullable', 'array'],
            'phases.*.name' => ['required_with:phases', 'string', 'max:255'],
            'phases.*.sequence' => ['nullable', 'integer', 'min:0'],

            'checklist' => ['nullable', 'array'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.required' => ['nullable', 'boolean'],
            'checklist.*.sequence' => ['nullable', 'integer', 'min:0'],
            'checklist.*.source_key' => ['nullable', 'string', 'max:255'],
            'checklist.*.completed' => ['nullable', 'boolean'],

            'sessions' => ['nullable', 'array'],
            'sessions.*.name' => ['required_with:sessions', 'string', 'max:255'],
            'sessions.*.session_date' => ['required_with:sessions', 'date'],
            'sessions.*.start_time' => ['nullable', 'date'],
            'sessions.*.end_time' => ['nullable', 'date'],
            'sessions.*.phase_index' => ['nullable', 'integer', 'min:0'],

            'venues' => ['nullable', 'array'],
            'venues.*.name' => ['required_with:venues', 'string', 'max:255'],
            'venues.*.address' => ['nullable', 'string', 'max:2000'],
            'venues.*.is_primary' => ['nullable', 'boolean'],

            'playbook_version_id' => ['nullable', 'integer', 'exists:playbook_versions,id'],
        ];
    }
}
