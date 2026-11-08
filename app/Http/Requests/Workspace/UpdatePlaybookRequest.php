<?php

namespace App\Http\Requests\Workspace;

use App\Models\Organization;
use App\Models\Playbook;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlaybookRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    protected array $configKeys = [
        'phases',
        'checklist',
        'public_schedule',
        'run_of_show',
        'team_roles',
        'vendor_categories',
        'reception',
        'invitation_defaults',
        'guestbook_defaults',
    ];

    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $playbook = $this->route('playbook');

        if (! $organization instanceof Organization || ! $playbook instanceof Playbook) {
            return false;
        }

        // Only organization-owned playbooks may be edited; platform templates
        // are read-only and cross-organization playbooks must not leak.
        if ($playbook->isPlatform() || (int) $playbook->organization_id !== (int) $organization->id) {
            abort(404);
        }

        return $this->user()?->can('managePlaybooks', $organization) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'days' => ['nullable', 'integer', 'between:1,30'],
            'config' => ['nullable', 'array'],
        ];

        foreach ($this->configKeys as $key) {
            $rules["config.{$key}"] = ['nullable', 'array'];
        }

        return $rules;
    }
}
