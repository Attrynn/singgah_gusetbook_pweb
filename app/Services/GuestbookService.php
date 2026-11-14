<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Enums\MessageStatus;
use App\Models\AppSetting;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuestbookService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * @param  bool  $enforceEventLifecycle  Memory contributions may keep accepting
     *                                       messages after an event is completed, so
     *                                       the caller can opt out of the generic
     *                                       guest-action gate while still passing the
     *                                       guestbook-enabled and guest-status checks.
     */
    public function submit(Guest $guest, array $data, bool $enforceEventLifecycle = true): GuestbookMessage
    {
        $guest->loadMissing('event');

        if (! $guest->event->guestbook_enabled) {
            throw ValidationException::withMessages([
                'message' => 'Guestbook untuk acara ini sedang dinonaktifkan.',
            ]);
        }

        if ($enforceEventLifecycle && ! $guest->event->allowsGuestActions()) {
            throw ValidationException::withMessages([
                'message' => 'Guestbook untuk acara ini belum dibuka atau sudah ditutup.',
            ]);
        }

        if ($guest->status !== GuestStatus::Active) {
            throw ValidationException::withMessages([
                'message' => 'Status undangan Anda tidak diizinkan mengirim ucapan.',
            ]);
        }

        $autoApprove = (string) AppSetting::get('guestbook_auto_approve', '0') === '1';

        $message = GuestbookMessage::create([
            'event_id' => $guest->event_id,
            'guest_id' => $guest->id,
            'sender_name' => $data['sender_name'] ?? $guest->name,
            'message' => trim($data['message']),
            'emoji' => $data['emoji'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'status' => $autoApprove ? MessageStatus::Approved : MessageStatus::Pending,
            'reviewed_at' => $autoApprove ? now() : null,
            'ip_address' => request()?->ip(),
        ]);

        $this->audit->guestAction(
            $guest->event,
            $guest,
            'guestbook.submitted',
            "Ucapan baru dari {$guest->name}".($autoApprove ? ' (otomatis disetujui)' : ''),
            [
                'visibility' => $message->visibility->value,
                'auto_approved' => $autoApprove,
            ]
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

        try {
            $user->guestbookFavorites()->create(['guestbook_message_id' => $message->id]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return true;
        }
    }
}
