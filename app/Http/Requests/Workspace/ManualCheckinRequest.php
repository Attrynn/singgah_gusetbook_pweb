<?php

namespace App\Http\Requests\Workspace;

use App\Authorization\PermissionRegistry;
use App\Models\Event;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class ManualCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->isSuperadmin() || $user?->isPlatformSuperadmin()) {
            return true;
        }

        $event = $this->route('event');

        if (! $event instanceof Event) {
            return false;
        }

        return app(PermissionService::class)
            ->hasEventPermission($user, $event, PermissionRegistry::CHECKIN_CREATE);
    }

    public function rules(): array
    {
        return [
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
