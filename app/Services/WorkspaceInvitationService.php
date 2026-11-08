<?php

namespace App\Services;

use App\Enums\EventOwnerRole;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\WorkspaceInvitationStatus;
use App\Enums\WorkspaceInvitationType;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WorkspaceInvitationService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Create a staff invitation.
     * $eventAssignments: array of ['event_id' => int, 'role' => string (EventStaffRole value)]
     */
    public function createStaffInvitation(
        Organization $org,
        User $inviter,
        string $email,
        OrganizationRole $organizationRole,
        array $eventAssignments = [],
    ): WorkspaceInvitation {
        $this->validateEventAssignments($org, $eventAssignments, 'staff');

        [$rawToken, $tokenHash] = $this->generateToken();

        $invitation = WorkspaceInvitation::create([
            'organization_id' => $org->id,
            'invited_by' => $inviter->id,
            'email' => strtolower(trim($email)),
            'type' => WorkspaceInvitationType::Staff->value,
            'organization_role' => $organizationRole->value,
            'event_assignments' => $eventAssignments,
            'token_hash' => $tokenHash,
            'status' => WorkspaceInvitationStatus::Pending->value,
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->record(
            'invitation.staff.created',
            "Staff invitation created for {$email} in {$org->name}",
            null,
            $inviter,
            $invitation,
        );

        Mail::to($email)->queue(new WorkspaceInvitationMail($invitation, $rawToken));

        return $invitation;
    }

    /**
     * Create a couple invitation.
     * $coupleRole: 'groom', 'bride', or 'partner'
     */
    public function createCoupleInvitation(
        Organization $org,
        User $inviter,
        string $email,
        int $eventId,
        EventOwnerRole $coupleRole,
    ): WorkspaceInvitation {
        // Validate event belongs to org
        $event = Event::where('id', $eventId)
            ->where('organization_id', $org->id)
            ->firstOrFail();

        [$rawToken, $tokenHash] = $this->generateToken();

        $invitation = WorkspaceInvitation::create([
            'organization_id' => $org->id,
            'invited_by' => $inviter->id,
            'email' => strtolower(trim($email)),
            'type' => WorkspaceInvitationType::Couple->value,
            'organization_role' => null,
            'event_assignments' => [['event_id' => $event->id, 'role_label' => $coupleRole->value]],
            'token_hash' => $tokenHash,
            'status' => WorkspaceInvitationStatus::Pending->value,
            'expires_at' => now()->addDays(30),
        ]);

        $this->audit->record(
            'invitation.couple.created',
            "Couple invitation ({$coupleRole->value}) created for {$email} for event #{$event->id}",
            null,
            $inviter,
            $invitation,
        );

        Mail::to($email)->queue(new WorkspaceInvitationMail($invitation, $rawToken));

        return $invitation;
    }

    /**
     * Accept an invitation. Validates token, email match, status, expiry.
     * Creates org membership and event assignments atomically.
     */
    public function acceptInvitation(string $rawToken, User $acceptingUser): WorkspaceInvitation
    {
        $tokenHash = hash('sha256', $rawToken);

        $invitation = WorkspaceInvitation::where('token_hash', $tokenHash)
            ->lockForUpdate()
            ->firstOrFail();

        if ($invitation->status !== WorkspaceInvitationStatus::Pending) {
            abort(422, 'Undangan ini sudah tidak dapat digunakan.');
        }

        if ($invitation->isExpired()) {
            $this->markExpired($invitation);
            abort(422, 'Undangan ini sudah kedaluwarsa.');
        }

        if (strtolower($invitation->email) !== strtolower($acceptingUser->email)) {
            abort(403, 'Email akun Anda tidak sesuai dengan target undangan ini.');
        }

        DB::transaction(function () use ($invitation, $acceptingUser): void {
            if ($invitation->type === WorkspaceInvitationType::Staff) {
                $this->activateStaffMembership($invitation, $acceptingUser);
            } else {
                $this->activateCoupleMembership($invitation, $acceptingUser);
            }

            $invitation->update([
                'status' => WorkspaceInvitationStatus::Accepted->value,
                'accepted_at' => now(),
                'accepted_by' => $acceptingUser->id,
            ]);
        });

        $this->audit->record(
            "invitation.{$invitation->type->value}.accepted",
            "Invitation accepted by {$acceptingUser->email}",
            null,
            $acceptingUser,
            $invitation,
        );

        return $invitation->fresh();
    }

    /**
     * Revoke a pending invitation.
     */
    public function revokeInvitation(WorkspaceInvitation $invitation, User $actor): void
    {
        if ($invitation->status !== WorkspaceInvitationStatus::Pending) {
            abort(422, 'Hanya undangan dengan status pending yang dapat dibatalkan.');
        }

        $invitation->update([
            'status' => WorkspaceInvitationStatus::Revoked->value,
            'revoked_at' => now(),
        ]);

        $this->audit->record(
            'invitation.revoked',
            "Invitation revoked for {$invitation->email}",
            null,
            $actor,
            $invitation,
        );
    }

    /**
     * Resend (regenerate token + reset expiry) for a pending or expired invitation.
     */
    public function resendInvitation(WorkspaceInvitation $invitation, User $actor): WorkspaceInvitation
    {
        if ($invitation->status === WorkspaceInvitationStatus::Revoked ||
            $invitation->status === WorkspaceInvitationStatus::Accepted) {
            abort(422, 'Undangan yang sudah diterima atau dicabut tidak dapat dikirim ulang.');
        }

        [$rawToken, $tokenHash] = $this->generateToken();

        $invitation->update([
            'token_hash' => $tokenHash,
            'status' => WorkspaceInvitationStatus::Pending->value,
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->record(
            'invitation.resent',
            "Invitation resent to {$invitation->email}",
            null,
            $actor,
            $invitation,
        );

        Mail::to($invitation->email)->queue(new WorkspaceInvitationMail($invitation, $rawToken));

        return $invitation->fresh();
    }

    /**
     * Mark expired invitations as expired. Called by scheduler or artisan command.
     */
    public function expireStaleInvitations(): int
    {
        return WorkspaceInvitation::where('status', WorkspaceInvitationStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => WorkspaceInvitationStatus::Expired->value]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function generateToken(): array
    {
        $raw = Str::random(64);
        $hash = hash('sha256', $raw);

        return [$raw, $hash];
    }

    private function markExpired(WorkspaceInvitation $invitation): void
    {
        $invitation->update(['status' => WorkspaceInvitationStatus::Expired->value]);
    }

    private function validateEventAssignments(Organization $org, array $assignments, string $type): void
    {
        foreach ($assignments as $assignment) {
            $eventId = $assignment['event_id'] ?? null;
            if (! $eventId) {
                abort(422, 'event_id wajib ada pada setiap event assignment.');
            }
            $exists = Event::where('id', $eventId)
                ->where('organization_id', $org->id)
                ->exists();
            if (! $exists) {
                abort(422, "Event #{$eventId} tidak ditemukan dalam organization ini.");
            }
            if ($type === 'staff') {
                $role = $assignment['role'] ?? null;
                if (! $role || ! EventStaffRole::tryFrom($role)) {
                    abort(422, "Role '{$role}' bukan event staff role yang valid.");
                }
            }
        }
    }

    private function activateStaffMembership(WorkspaceInvitation $invitation, User $user): void
    {
        // Upsert org membership
        $existing = OrganizationMembership::where('organization_id', $invitation->organization_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $existing) {
            OrganizationMembership::create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->id,
                'role' => $invitation->organization_role ?? OrganizationRole::Member->value,
                'status' => OrganizationMembershipStatus::Active->value,
                'invited_by' => $invitation->invited_by,
                'accepted_at' => now(),
            ]);
        } elseif (! $existing->isActive()) {
            $existing->update([
                'status' => OrganizationMembershipStatus::Active->value,
                'accepted_at' => now(),
            ]);
        }

        // Create event staff assignments
        foreach ($invitation->event_assignments ?? [] as $assignment) {
            EventStaffMembership::updateOrCreate(
                [
                    'event_id' => $assignment['event_id'],
                    'user_id' => $user->id,
                    'role' => $assignment['role'],
                ],
                [
                    'status' => MembershipStatus::Active->value,
                    'assigned_by' => $invitation->invited_by,
                ]
            );
        }
    }

    private function activateCoupleMembership(WorkspaceInvitation $invitation, User $user): void
    {
        $assignments = $invitation->event_assignments ?? [];
        foreach ($assignments as $assignment) {
            EventOwnerMembership::updateOrCreate(
                [
                    'event_id' => $assignment['event_id'],
                    'user_id' => $user->id,
                ],
                [
                    'role_label' => $assignment['role_label'],
                    'status' => MembershipStatus::Active->value,
                    'invited_by' => $invitation->invited_by,
                    'accepted_at' => now(),
                ]
            );
        }
        // Couple invitation does NOT create an OrganizationMembership.
    }
}
