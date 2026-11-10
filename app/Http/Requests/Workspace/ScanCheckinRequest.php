<?php

namespace App\Http\Requests\Workspace;

use App\Authorization\PermissionRegistry;
use App\Models\Event;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class ScanCheckinRequest extends FormRequest
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
            ->hasEventPermission($user, $event, PermissionRegistry::CHECKIN_SCAN);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:500'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ];
    }
}
