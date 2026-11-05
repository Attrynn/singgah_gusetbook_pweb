<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\GuestbookMessage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GuestbookService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function submit(\App\Models\Guest $guest, array $data): GuestbookMessage
    {
        $guest->loadMissing('event');

        if (! $guest->event->guestbook_enabled) {
            throw ValidationException::withMessages([
                'message' => 'Guestbook untuk acara ini sedang dinonaktifkan.',
            ]);
        }

        $message = GuestbookMessage::create([
            'event_id' => $guest->event_id,
            'guest_id' => $guest->id,
            'sender_name' => $guest->name,
            'message' => trim($data['message']),
            'emoji' => $data['emoji'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'status' => MessageStatus::Pending,
            'ip_address' => request()?->ip(),
        ]);

        $this->audit->guestAction(
            $guest->event,
            $guest,
            'guestbook.submitted',
            "Ucapan baru dari {$guest->name}",
            ['visibility' => $message->visibility->value]
        );

        return $message;
    }

    public function moderate(GuestbookMessage $message, string $action, User $actor): GuestbookMessage
    {
        $status = match ($action) {
            'approve' => MessageStatus::Approved,
            'reject' => MessageStatus::Rejected,
            'hide' => MessageStatus::Hidden,
            'pending' => MessageStatus::Pending,
            default => throw ValidationException::withMessages(['action' => 'Aksi moderasi tidak dikenal.']),
        };

        $message->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->record(
            'guestbook.'.$action,
            "Ucapan {$message->sender_name} di-{$action}",
            $message->event,
            $actor,
            $message,
            ['status' => $status->value]
        );

        return $message->fresh();
    }

    public function togglePin(GuestbookMessage $message, User $actor): GuestbookMessage
    {
        $message->update(['is_pinned' => ! $message->is_pinned]);
        $this->audit->record('guestbook.pin', "Pin ucapan {$message->sender_name}", $message->event, $actor, $message);

        return $message->fresh();
    }

    public function delete(GuestbookMessage $message, User $actor): void
    {
        $this->audit->record('guestbook.deleted', "Ucapan dihapus: {$message->sender_name}", $message->event, $actor, $message);
        $message->delete();
    }

    public function toggleFavorite(GuestbookMessage $message, User $user): bool
    {
        $existing = $user->guestbookFavorites()->where('guestbook_message_id', $message->id)->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $user->guestbookFavorites()->create(['guestbook_message_id' => $message->id]);

        return true;
    }
}
