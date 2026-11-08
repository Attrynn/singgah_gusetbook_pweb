<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\SetupStatus;
use App\Models\Event;
use App\Models\EventSetupConfig;
use App\Models\EventStaffMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manages direct staff assignment for a single event.
 *
 * Assignment reuses workspace members as event staff; a staff user must already
 * hold an active membership in the event's organization. Primary PIC is a
 * reference to one active staff membership, surfaced on the setup config.
 */
class EventTeamService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function assign(Event $event, User $target, EventStaffRole $role, User $actor): EventStaffMembership
    {
        $this->assertActorMayManageTeam($event, $actor);
        $this->assertTargetIsActiveOrgMember($event, $target);
        $this->assertTargetCanAccessEvent($event, $target);

        return DB::transaction(function () use ($event, $target, $role, $actor): EventStaffMembership {
            $membership = EventStaffMembership::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $target->id,
                    'role' => $role,
                ],
                [
                    'status' => MembershipStatus::Active,
                    'revoked_at' => null,
                    'assigned_by' => $actor->id,
                ],
            );

            $this->audit->record(
                'team.assigned',
                "{$target->name} ditugaskan sebagai {$role->label()} pada {$event->name}",
                $event,
                $actor,
                $membership,
                ['user_id' => $target->id, 'role' => $role->value],
            );

            return $membership->fresh();
        });
    }

    public function remove(Event $event, EventStaffMembership $membership, User $actor): void
    {
        $this->assertActorMayManageTeam($event, $actor);

        if ((int) $membership->event_id !== (int) $event->id) {
            abort(422, 'Anggota tim ini bukan bagian dari event tersebut.');
        }

        DB::transaction(function () use ($event, $membership, $actor): void {
            $membership->update([
                'status' => MembershipStatus::Revoked,
                'revoked_at' => now(),
            ]);

            // Never leave a revoked membership dangling as the primary PIC.
            EventSetupConfig::query()
                ->where('event_id', $event->id)
                ->where('primary_pic_staff_membership_id', $membership->id)
                ->update(['primary_pic_staff_membership_id' => null]);

            // Run of show items may still reference this membership as their PIC.
            DB::table('event_run_of_show_items')
                ->where('event_id', $event->id)
                ->where('pic_staff_membership_id', $membership->id)
                ->update(['pic_staff_membership_id' => null]);

            $this->audit->record(
                'team.revoked',
                "Akses tim dicabut pada {$event->name}",
                $event,
                $actor,
                $membership,
                ['user_id' => $membership->user_id, 'role' => $membership->role->value],
            );
        });
    }

    public function setPrimaryPic(Event $event, ?EventStaffMembership $membership, User $actor): EventSetupConfig
    {
        $this->assertActorMayManageTeam($event, $actor);

        if ($membership !== null) {
            $isActiveSameEvent = (int) $membership->event_id === (int) $event->id
                && $membership->status === MembershipStatus::Active;

            if (! $isActiveSameEvent) {
                abort(422, 'Primary PIC harus anggota tim aktif dari event ini.');
            }
        }

        return DB::transaction(function () use ($event, $membership, $actor): EventSetupConfig {
            $setup = $event->setupConfig()->first()
                ?? EventSetupConfig::create([
                    'event_id' => $event->id,
                    'config_snapshot' => [],
                    'setup_status' => SetupStatus::NotStarted,
                ]);

            $setup->update([
                'primary_pic_staff_membership_id' => $membership?->id,
            ]);

            $this->audit->record(
                'team.primary_pic',
                "Primary PIC {$event->name} diperbarui",
                $event,
                $actor,
                $setup,
                ['primary_pic_staff_membership_id' => $membership?->id],
            );

            return $setup->fresh();
        });
    }

    protected function assertActorMayManageTeam(Event $event, User $actor): void
    {
        if (! $this->permissions->hasEventPermission($actor, $event, PermissionRegistry::EVENT_TEAM_MANAGE)) {
            abort(403, 'Anda tidak berwenang mengelola tim event ini.');
        }
    }

    protected function assertTargetIsActiveOrgMember(Event $event, User $target): void
    {
        if (! $event->organization_id) {
            abort(422, 'Event belum terhubung ke workspace.');
        }

        if (! $target->organizationMembershipFor((int) $event->organization_id)?->isActive()) {
            abort(422, 'User bukan anggota workspace aktif untuk event ini.');
        }
    }

    protected function assertTargetCanAccessEvent(Event $event, User $target): void
    {
        if (! $target->canAccessEvent($event)) {
            abort(422, 'User tidak memiliki akses ke event ini.');
        }
    }
}
