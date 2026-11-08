<?php

namespace App\Http\Requests\Workspace;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceEventRequest extends FormRequest
{
    /**
     * Creation defaults to Draft; a dedicated status endpoint handles lifecycle.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        if (! $organization instanceof Organization) {
            return false;
        }

        return $this->user()?->can('create', [Event::class, $organization]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'groom_name' => ['nullable', 'string', 'max:255'],
            'bride_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string', 'max:2000'],
            'google_maps_url' => ['nullable', 'url', 'max:2048'],
            'timezone' => ['required', 'timezone', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'playbook_version_id' => ['nullable', 'integer', 'exists:playbook_versions,id'],
            'duplicate_source_event_id' => ['nullable', 'integer', 'exists:events,id'],
        ];
    }
}
