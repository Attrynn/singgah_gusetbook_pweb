<?php

namespace App\Services;

use App\Enums\GuestPartyStatus;
use App\Enums\InvitationStatus;
use App\Enums\RsvpStatus;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\Invitation;
use App\Models\Rsvp;
use Illuminate\Support\Carbon;

/**
 * Event-scoped attendance analytics.
 *
 * Every metric below is derived from persisted rows and has an explicit
 * definition. The counts are deliberately NOT interchangeable:
 *
 *  - quota               = sum of guests.max_attendees (invitation capacity)
 *  - identified          = active guest_party_memberships (named individuals)
 *  - unidentified        = max(0, quota - identified) (never fabricated rows)
 *  - rsvp_attendance     = sum of rsvps.attendance_count where confirmed (legacy)
 *  - passes_*            = guest_passes by lifecycle status
 *  - checkins            = actual admission records
 */
class AttendanceService
{
    /**
     * @return array{
     *     invitations: array{parties:int, quota:int, created:int, sent:int, opened:int},
     *     guests: array{identified:int, unidentified_capacity:int, active_memberships:int},
     *     rsvp: array{confirmed:int, declined:int, pending:int, maybe:int, attendance_count:int},
     *     passes: array{issued:int, active:int, revoked:int, expired:int, unused:int},
     *     checkins: array{total:int, not_checked_in:int, completion_percentage:int}
     * }
     */
    public function dashboardStats(Event $event): array
    {
        $eventId = $event->id;

        $parties = Guest::query()->where('event_id', $eventId);
        $totalParties = (clone $parties)->count();
        $totalQuota = (int) (clone $parties)->sum('max_attendees');

        $invitationQuery = Invitation::query()->where('event_id', $eventId);
        $invitationsCreated = (clone $invitationQuery)->count();
        $invitationsSent = (clone $invitationQuery)->where('status', InvitationStatus::Sent->value)->count();
        $invitationsOpened = (clone $invitationQuery)->whereNotNull('opened_at')->count();

        $activeMemberships = GuestPartyMembership::query()
            ->where('event_id', $eventId)
            ->where('status', GuestPartyStatus::Active->value)
            ->count();

        $rsvpQuery = Rsvp::query()->where('event_id', $eventId);
        $confirmed = (clone $rsvpQuery)->where('status', RsvpStatus::Confirmed->value)->count();
        $declined = (clone $rsvpQuery)->where('status', RsvpStatus::Declined->value)->count();
        $pending = (clone $rsvpQuery)->where('status', RsvpStatus::Pending->value)->count();
        $maybe = (clone $rsvpQuery)->where('status', RsvpStatus::Maybe->value)->count();
        $rsvpAttendance = (int) (clone $rsvpQuery)->where('status', RsvpStatus::Confirmed->value)->sum('attendance_count');

        $passQuery = GuestPass::query()->where('event_id', $eventId);
        $passesIssued = (clone $passQuery)->count();
        $passesActive = (clone $passQuery)->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        $passesRevoked = (clone $passQuery)->where('status', 'revoked')->count();
        $passesExpired = (clone $passQuery)->where('status', '!=', 'revoked')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();
        $passesUnused = (clone $passQuery)->where('status', 'active')
            ->whereNull('revoked_at')
            ->whereNull('last_used_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $totalCheckins = Checkin::query()->where('event_id', $eventId)->count();
        $notCheckedIn = max(0, $activeMemberships - $totalCheckins);
        $completion = $activeMemberships > 0
            ? (int) round(($totalCheckins / $activeMemberships) * 100)
            : 0;

        return [
            'invitations' => [
                'parties' => $totalParties,
                'quota' => $totalQuota,
                'created' => $invitationsCreated,
                'sent' => $invitationsSent,
                'opened' => $invitationsOpened,
            ],
            'guests' => [
                'identified' => $activeMemberships,
                'unidentified_capacity' => max(0, $totalQuota - $activeMemberships),
                'active_memberships' => $activeMemberships,
            ],
            'rsvp' => [
                'confirmed' => $confirmed,
                'declined' => $declined,
                'pending' => $pending,
                'maybe' => $maybe,
                'attendance_count' => $rsvpAttendance,
            ],
            'passes' => [
                'issued' => $passesIssued,
                'active' => $passesActive,
                'revoked' => $passesRevoked,
                'expired' => $passesExpired,
                'unused' => $passesUnused,
            ],
            'checkins' => [
                'total' => $totalCheckins,
                'not_checked_in' => $notCheckedIn,
                'completion_percentage' => min(100, $completion),
            ],
        ];
    }

    /**
     * Check-in distribution grouped by hour for the event day.
     *
     * The bucketing is done in PHP so the same code works on MySQL and SQLite
     * (production uses MySQL).
     *
     * @return array<int, array{hour:string, count:int}>
     */
    public function checkinDistribution(Event $event): array
    {
        $groups = [];

        foreach (Checkin::where('event_id', $event->id)->pluck('checked_in_at') as $timestamp) {
            if (! $timestamp) {
                continue;
            }

            $bucket = Carbon::parse((string) $timestamp)->format('Y-m-d H:00');

            $groups[$bucket] = ($groups[$bucket] ?? 0) + 1;
        }

        ksort($groups);

        return array_map(
            fn (string $bucket, int $count): array => ['hour' => $bucket, 'count' => $count],
            array_keys($groups),
            array_values($groups),
        );
    }
}
