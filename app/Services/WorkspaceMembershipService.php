<?php

namespace App\Services;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkspaceMembershipService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Change a member's workspace role, protecting the last active Owner.
     */
    public function changeRole(Organization $org, User $target, OrganizationRole $newRole, User $actor): OrganizationMembership
    {
        return DB::transaction(function () use ($org, $target, $newRole, $actor): OrganizationMembership {
            $membership = $this->lockedMembership($org, $target);

            $this->assertActorMayManage($org, $membership, $actor);

            if ($membership->role === OrganizationRole::Owner && $newRole !== OrganizationRole::Owner) {
                $this->assertNotLastActiveOwner($org, $membership);
            }

            $membership->update(['role' => $newRole->value]);

            $this->audit->record(
                'membership.role_changed',
                "Role {$target->email} diubah menjadi {$newRole->value} di {$org->name}",
                null,
                $actor,
                $membership,
            );

            return $membership->fresh();
        });
    }

    /**
     * Revoke a member's access to the workspace.
     */
    public function revoke(Organization $org, User $target, User $actor): void
    {
        DB::transaction(function () use ($org, $target, $actor): void {
            $membership = $this->lockedMembership($org, $target);

            $this->assertActorMayManage($org, $membership, $actor);

            if ($membership->role === OrganizationRole::Owner) {
                $this->assertNotLastActiveOwner($org, $membership);
            }

            $membership->update(['status' => OrganizationMembershipStatus::Revoked->value]);

            $this->audit->record(
                'membership.revoked',
                "Akses {$target->email} dicabut dari {$org->name}",
                null,
                $actor,
                $membership,
            );
        });
    }

    /**
     * Temporarily suspend a member's access to the workspace.
     */
    public function suspend(Organization $org, User $target, User $actor): void
    {
        DB::transaction(function () use ($org, $target, $actor): void {
            $membership = $this->lockedMembership($org, $target);

            $this->assertActorMayManage($org, $membership, $actor);

            if ($membership->role === OrganizationRole::Owner) {
                $this->assertNotLastActiveOwner($org, $membership);
            }

            $membership->update(['status' => OrganizationMembershipStatus::Suspended->value]);

            $this->audit->record(
                'membership.suspended',
                "Akses {$target->email} ditangguhkan di {$org->name}",
                null,
                $actor,
                $membership,
            );
        });
    }

    /**
     * Restore a suspended/revoked membership back to active.
     */
    public function restore(Organization $org, User $target, User $actor): void
    {
        DB::transaction(function () use ($org, $target, $actor): void {
            $membership = $this->lockedMembership($org, $target);

            $membership->update([
                'status' => OrganizationMembershipStatus::Active->value,
                'accepted_at' => now(),
            ]);

            $this->audit->record(
                'membership.restored',
                "Akses {$target->email} dipulihkan di {$org->name}",
                null,
                $actor,
                $membership,
            );
        });
    }

    /**
     * Lock and return the target's membership row, aborting when absent.
     */
    private function lockedMembership(Organization $org, User $target): OrganizationMembership
    {
        $membership = $org->memberships()
            ->where('user_id', $target->id)
            ->lockForUpdate()
            ->first();

        if (! $membership) {
            abort(422, 'User bukan anggota workspace ini.');
        }

        return $membership;
    }

    /**
     * Abort when the given membership is the only active Owner of the workspace.
     */
    private function assertNotLastActiveOwner(Organization $org, OrganizationMembership $membership): void
    {
        if ($membership->role !== OrganizationRole::Owner || ! $membership->isActive()) {
            return;
        }

        $activeOwners = $org->owners()->lockForUpdate()->count();

        if ($activeOwners <= 1) {
            abort(422, 'Workspace harus memiliki minimal satu Workspace Owner aktif.');
        }
    }

    /**
     * Defense-in-depth authorization: an actor may only manage a target whose
     * role is at least as privileged as their own, and may never revoke/suspend
     * an Owner unless they are themselves an Owner (or a platform superadmin).
     */
    private function assertActorMayManage(Organization $org, OrganizationMembership $target, User $actor): void
    {
        if ($actor->isSuperadmin() || $actor->isPlatformSuperadmin()) {
            return;
        }

        $actorRole = $org->membershipFor($actor)?->role;

        if ($target->role === OrganizationRole::Owner && $actorRole !== OrganizationRole::Owner) {
            abort(422, 'Hanya Workspace Owner yang dapat mengelola Workspace Owner lain.');
        }

        if ($actorRole === null) {
            return;
        }

        if (! $actorRole->isAtLeast($target->role)) {
            abort(422, 'Anda tidak dapat mengelola anggota dengan role di atas kewenangan Anda.');
        }
    }
}
