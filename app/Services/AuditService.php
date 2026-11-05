<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function record(
        string $action,
        string $description,
        ?Event $event = null,
        ?User $user = null,
        ?Model $target = null,
        array $metadata = [],
    ): ?AuditLog {
        try {
            $request = request();

            return AuditLog::create([
                'user_id' => ($user ?? auth()->user())?->id,
                'event_id' => $event?->id,
                'action' => $action,
                'target_type' => $target ? $target::class : null,
                'target_id' => $target?->getKey(),
                'description' => $description,
                'metadata' => $metadata ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log failed: '.$e->getMessage());

            return null;
        }
    }

    public function activity(
        Event $event,
        string $type,
        string $description,
        ?Guest $guest = null,
        ?User $user = null,
        array $metadata = [],
    ): ?EventActivity {
        try {
            return EventActivity::create([
                'event_id' => $event->id,
                'user_id' => ($user ?? auth()->user())?->id,
                'guest_id' => $guest?->id,
                'type' => $type,
                'description' => $description,
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Event activity failed: '.$e->getMessage());

            return null;
        }
    }

    public function guestAction(
        Event $event,
        Guest $guest,
        string $type,
        string $description,
        array $metadata = [],
    ): void {
        $this->activity($event, $type, $description, $guest, null, $metadata);
        $this->record($type, $description, $event, null, $guest, $metadata);
    }
}
