<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\EventRunOfShowAssignee;
use App\Models\EventStaffMembership;
use App\Models\EventStaffPermissionGrant;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped staff lifecycle and permission delegation.
 *
 * Every transition is explicit, authorized, audited, and revokes operational
 * responsibilities before a membership loses its authorization.
 */
class StaffOperationsService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    /**
     * Invite an existing workspace member into the event's operational team.
     */
    public function invite(Event $event, User $target, EventStaffRole $role, User $actor, array $options = []): EventStaffMembership
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STAFF_INVITE, 'Anda tidak berwenang mengundang staf event ini.');
        $this->assertTargetIsActiveOrgMember($event, $target);
        $this->assertRoleDoesNotEscalate($event, $actor, $role);

        return DB::transaction(function () use ($event, $target, $role, $actor, $options): EventStaffMembership {
            $existing = EventStaffMembership::query()
                ->where('event_id', $event->id)
                ->where('user_id', $target->id)
                ->where('role', $role->value)
                ->first();

            if ($existing?->status === MembershipStatus::Active) {
                throw ValidationException::withMessages([
                    'user_id' => 'Pengguna ini sudah memiliki peran tersebut pada event ini.',
                ]);
            }

            $membership = EventStaffMembership::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $target->id,
                    'role' => $role->value,
                ],
                [
                    'status' => MembershipStatus::Pending,
                    'assigned_by' => $actor->id,
                    'invited_by' => $actor->id,
                    'invited_at' => now(),
                    'accepted_at' => null,
                    'declined_at' => null,
                    'suspended_at' => null,
                    'removed_at' => null,
                    'revoked_at' => null,
                    'expires_at' => $options['expires_at'] ?? null,
                    'notes' => $options['notes'] ?? null,
                ],
            );

            $this->audit->record(
                'staff.invited',
                "{$target->name} diundang sebagai {$role->label()} pada {$event->name}",
                $event,
                $actor,
                $membership,
                ['user_id' => $target->id, 'role' => $role->value],
            );

            return $membership->fresh();
        });
    }

    public function accept(Event $event, EventStaffMembership $membership, User $actor): EventStaffMembership
    {
        $this->assertMembershipBelongsToEvent($event, $membership);
        abort_unless((int) $membership->user_id === (int) $actor->id, 403, 'Hanya penerima undangan yang dapat menerima.');

        if ($membership->status !== MembershipStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Undangan ini tidak lagi menunggu konfirmasi.']);
        }

        return DB::transaction(function () use ($event, $membership, $actor): EventStaffMembership {
            $membership->update([
                'status' => MembershipStatus::Active,
                'accepted_at' => now(),
            ]);

            $this->audit->record(
                'staff.accepted',
                "{$actor->name} menerima peran {$membership->role->label()} pada {$event->name}",
                $event,
                $actor,
                $membership,
            );

            return $membership->fresh();
        });
    }

    public function decline(Event $event, EventStaffMembership $membership, User $actor): EventStaffMembership
    {
        $this->assertMembershipBelongsToEvent($event, $membership);
        abort_unless((int) $membership->user_id === (int) $actor->id, 403, 'Hanya penerima undangan yang dapat menolak.');

        if ($membership->status !== MembershipStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Undangan ini tidak lagi menunggu konfirmasi.']);
        }

        return DB::transaction(function () use ($event, $membership, $actor): EventStaffMembership {
            $membership->update([
                'status' => MembershipStatus::Declined,
                'declined_at' => now(),
            ]);

            $this->audit->record(
                'staff.declined',
                "{$actor->name} menolak peran {$membership->role->label()} pada {$event->name}",
                $event,
                $actor,
                $membership,
            );

            return $membership->fresh();
        });
    }

    public function suspend(Event $event, EventStaffMembership $membership, User $actor, ?string $reason = null): EventStaffMembership
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STAFF_MANAGE, 'Anda tidak berwenang menangguhkan staf.');
        $this->assertMembershipBelongsToEvent($event, $membership);
        $this->assertNotSelf($membership, $actor, 'Anda tidak dapat menangguhkan keanggotaan Anda sendiri.');

        if ($membership->status !== MembershipStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Hanya staf aktif yang dapat ditangguhkan.']);
        }

        return DB::transaction(function () use ($event, $membership, $actor, $reason): EventStaffMembership {
            $this->releaseOperationalResponsibilities($event, $membership, $actor, 'Staf ditangguhkan'.($reason ? ': '.$reason : ''));

            $membership->update([
                'status' => MembershipStatus::Suspended,
                'suspended_at' => now(),
                'notes' => $reason ?? $membership->notes,
            ]);

            $this->audit->record(
                'staff.suspended',
                "Akses {$membership->user->name} ditangguhkan pada {$event->name}",
                $event,
                $actor,
                $membership,
                ['reason' => $reason],
            );

            return $membership->fresh();
        });
    }

    public function reinstate(Event $event, EventStaffMembership $membership, User $actor): EventStaffMembership
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STAFF_MANAGE, 'Anda tidak berwenang memulihkan staf.');
        $this->assertMembershipBelongsToEvent($event, $membership);

        if ($membership->status !== MembershipStatus::Suspended) {
            throw ValidationException::withMessages(['status' => 'Hanya staf yang ditangguhkan yang dapat dipulihkan.']);
        }

        return DB::transaction(function () use ($event, $membership, $actor): EventStaffMembership {
            $membership->update([
                'status' => MembershipStatus::Active,
                'suspended_at' => null,
                'accepted_at' => $membership->accepted_at ?? now(),
            ]);

            $this->audit->record(
                'staff.reinstated',
                "Akses {$membership->user->name} dipulihkan pada {$event->name}",
                $event,
                $actor,
                $membership,
            );

            return $membership->fresh();
        });
    }

    public function remove(Event $event, EventStaffMembership $membership, User $actor): EventStaffMembership
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STAFF_MANAGE, 'Anda tidak berwenang menghapus staf.');
        $this->assertMembershipBelongsToEvent($event, $membership);
        $this->assertNotSelf($membership, $actor, 'Anda tidak dapat menghapus keanggotaan Anda sendiri.');

        if (in_array($membership->status, [MembershipStatus::Removed, MembershipStatus::Revoked], true)) {
            return $membership;
        }

        return DB::transaction(function () use ($event, $membership, $actor): EventStaffMembership {
            $this->releaseOperationalResponsibilities($event, $membership, $actor, 'Akses staf dicabut');

            $membership->update([
                'status' => MembershipStatus::Removed,
                'removed_at' => now(),
                'revoked_at' => now(),
            ]);

            EventStaffPermissionGrant::query()
                ->where('event_staff_membership_id', $membership->id)
                ->delete();

            $this->audit->record(
                'staff.removed',
                "Akses {$membership->user->name} dicabut dari {$event->name}",
                $event,
                $actor,
                $membership,
            );

            return $membership->fresh();
        });
    }

    /**
     * Replace the explicit permission grants for an active membership.
     *
     * @param  array<int, string>  $permissions
     */
    public function grantPermissions(Event $event, EventStaffMembership $membership, array $permissions, User $actor): EventStaffMembership
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STAFF_PERMISSIONS_MANAGE, 'Anda tidak berwenang mengubah delegasi izin.');
        $this->assertMembershipBelongsToEvent($event, $membership);

        if ($membership->status !== MembershipStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Delegasi izin hanya berlaku untuk staf aktif.']);
        }

        $permissions = array_values(array_unique(array_filter($permissions)));

        foreach ($permissions as $permission) {
            if (! $this->permissions->mayDelegateEventPermission($actor, $event, $permission)) {
                throw ValidationException::withMessages([
                    'permissions' => "Anda tidak dapat mendelegasikan izin: {$permission}.",
                ]);
            }
        }

        return DB::transaction(function () use ($event, $membership, $permissions, $actor): EventStaffMembership {
            EventStaffPermissionGrant::query()
                ->where('event_staff_membership_id', $membership->id)
                ->delete();

            foreach ($permissions as $permission) {
                EventStaffPermissionGrant::create([
                    'event_id' => $event->id,
                    'event_staff_membership_id' => $membership->id,
                    'permission' => $permission,
                    'granted_by' => $actor->id,
                ]);
            }

            $this->audit->record(
                'staff.permissions_updated',
                "Delegasi izin {$membership->user->name} diperbarui pada {$event->name}",
                $event,
                $actor,
                $membership,
                ['permissions' => $permissions],
            );

            return $membership->fresh();
        });
    }

    /**
     * @return array<int, string>
     */
    public function effectivePermissions(EventStaffMembership $membership): array
    {
        $permissions = PermissionRegistry::forEventStaffRole($membership->role);

        $granted = $membership->permissionGrants()->pluck('permission')->all();

        return array_values(array_unique(array_merge($permissions, $granted)));
    }

    protected function releaseOperationalResponsibilities(Event $event, EventStaffMembership $membership, User $actor, string $reason): void
    {
        $membership->stationAssignments()
            ->whereNull('removed_at')
            ->update(['removed_at' => now()]);

        EventStation::query()
            ->where('event_id', $event->id)
            ->where('supervisor_membership_id', $membership->id)
            ->update(['supervisor_membership_id' => null]);

        EventRunOfShowAssignee::query()
            ->where('event_id', $event->id)
            ->where('staff_membership_id', $membership->id)
            ->delete();

        DB::table('event_run_of_show_items')
            ->where('event_id', $event->id)
            ->where('pic_staff_membership_id', $membership->id)
            ->update(['pic_staff_membership_id' => null, 'updated_by' => $actor->id]);

        EventTask::query()
            ->where('event_id', $event->id)
            ->where('assigned_membership_id', $membership->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->update([
                'assigned_membership_id' => null,
                'status' => 'blocked',
                'blocked_reason' => $reason,
                'updated_by' => $actor->id,
            ]);
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        if (! $this->permissions->hasEventPermission($actor, $event, $permission)) {
            abort(403, $message);
        }
    }

    protected function assertTargetIsActiveOrgMember(Event $event, User $target): void
    {
        if (! $event->organization_id) {
            throw ValidationException::withMessages(['user_id' => 'Event belum terhubung ke workspace.']);
        }

        if (! $target->organizationMembershipFor((int) $event->organization_id)?->isActive()) {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna harus menjadi anggota workspace aktif. Undang melalui menu Anggota Workspace terlebih dahulu.',
            ]);
        }
    }

    protected function assertRoleDoesNotEscalate(Event $event, User $actor, EventStaffRole $role): void
    {
        $actorPermissions = $this->permissions->effectiveEventPermissions($actor, $event);
        $rolePermissions = PermissionRegistry::forEventStaffRole($role);

        if (array_diff($rolePermissions, $actorPermissions) !== []) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat memberikan peran dengan izin melebihi wewenang Anda.',
            ]);
        }
    }

    protected function assertMembershipBelongsToEvent(Event $event, EventStaffMembership $membership): void
    {
        abort_unless((int) $membership->event_id === (int) $event->id, 404);
    }

    protected function assertNotSelf(EventStaffMembership $membership, User $actor, string $message): void
    {
        if ((int) $membership->user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['membership' => $message]);
        }
    }
}
