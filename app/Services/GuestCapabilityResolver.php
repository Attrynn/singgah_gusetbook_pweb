<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\GuestStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\Guest;
use App\Models\Poll;

/**
 * Central guest capability resolver.
 *
 * Navigation and backend authorization must both use these values so a CTA is
 * never shown when the action would be rejected, and a hidden feature is never
 * reachable through a direct HTTP request.
 */
class GuestCapabilityResolver
{
    /**
     * @return array<string, bool>
     */
    public function for(Event $event, Guest $guest): array
    {
        $active = $guest->status === GuestStatus::Active;
        $public = $event->isPublic();
        $actions = $event->allowsGuestActions();

        return [
            'invitation' => $active && $public,
            'rsvp' => $active && $actions && (bool) $event->rsvp_enabled && $event->rsvpOpen(),
            'guestbook' => $active && $actions && (bool) $event->guestbook_enabled,
            'gallery' => $active && $public && (bool) $event->gallery_enabled,
            'gallery_upload' => $active && $actions && (bool) $event->gallery_enabled,
            'gift' => $active && $public && (bool) $event->gift_enabled,
            'pass' => $active && $actions,
            'interactions' => $active && $actions,
            'song_request' => $active && $actions,
            'live_wall' => $public && (bool) $event->live_wall_enabled,
        ];
    }

    public function pollOpen(Poll $poll): bool
    {
        if (! $poll->is_active) {
            return false;
        }

        return $poll->closes_at === null || $poll->closes_at->isFuture();
    }

    public function gameOpen(Game $game): bool
    {
        return (bool) $game->is_active;
    }

    /**
     * Human-readable reason a capability is unavailable, for UI tooltips.
     */
    public function reason(string $capability, Event $event, Guest $guest): ?string
    {
        if ($guest->status === GuestStatus::Blocked) {
            return 'Akses tamu ini diblokir.';
        }

        if ($guest->status === GuestStatus::Inactive) {
            return 'Undangan untuk tamu ini sedang dinonaktifkan.';
        }

        if (! $event->isPublic()) {
            return 'Acara belum dipublikasikan.';
        }

        if (! $event->allowsGuestActions() && in_array($capability, ['rsvp', 'guestbook', 'gallery_upload', 'pass', 'interactions'], true)) {
            return $event->status === EventStatus::Completed
                ? 'Acara telah selesai; interaksi sudah ditutup.'
                : 'Interaksi tamu belum dibuka.';
        }

        return match ($capability) {
            'rsvp' => $event->rsvp_enabled ? 'Batas waktu RSVP telah berakhir.' : 'RSVP tidak diaktifkan untuk acara ini.',
            'guestbook' => 'Buku tamu tidak diaktifkan untuk acara ini.',
            'gallery', 'gallery_upload' => 'Galeri tidak diaktifkan untuk acara ini.',
            'gift' => 'Informasi hadiah tidak diaktifkan untuk acara ini.',
            'live_wall' => 'Live wall tidak diaktifkan untuk acara ini.',
            default => null,
        };
    }
}
