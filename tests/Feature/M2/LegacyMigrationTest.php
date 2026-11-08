<?php

namespace Tests\Feature\M2;

use App\Console\Commands\MigrateEventUserToOwnerMemberships;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * Seed realistic legacy event_user rows across several events.
     *
     * @return array<int, array{0:int,1:int,2:string}> [event_id, user_id, role_label] tuples
     */
    protected function seedLegacyRows(int $events = 2, int $usersPerEvent = 2): array
    {
        $inserts = [];
        $tuples = [];
        $roles = ['groom', 'bride', 'partner'];

        for ($e = 0; $e < $events; $e++) {
            Organization::factory()->create(['status' => 'active']);
            $event = Event::factory()->create(['organization_id' => null]);

            for ($u = 0; $u < $usersPerEvent; $u++) {
                $user = User::factory()->create(['is_active' => true]);
                $roleLabel = $roles[$u] ?? 'partner';

                $inserts[] = [
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'role_label' => $roleLabel,
                ];

                $tuples[] = [$event->id, $user->id, $roleLabel];
            }
        }

        DB::table('event_user')->insert($inserts);

        return $tuples;
    }

    public function test_command_migrates_every_valid_event_user_row_preserving_ids_and_roles(): void
    {
        $originalRows = $this->seedLegacyRows(2, 2);
        $originalCount = DB::table('event_user')->count();
        $this->assertSame($originalCount, count($originalRows));

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();

        $this->assertSame($originalCount, DB::table('event_user')->count());
        $this->assertSame($originalCount, DB::table('event_owner_memberships')->count());

        foreach ($originalRows as [$eventId, $userId, $roleLabel]) {
            $this->assertDatabaseHas('event_owner_memberships', [
                'event_id' => $eventId,
                'user_id' => $userId,
                'role_label' => $roleLabel,
                'status' => 'active',
            ]);
        }
    }

    public function test_migration_is_idempotent_and_second_run_skips_all(): void
    {
        $this->seedLegacyRows(3, 2);

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();
        $this->assertSame(6, DB::table('event_owner_memberships')->count());

        // Second run: counts stay stable and every row is reported as skipped.
        $this->artisan(MigrateEventUserToOwnerMemberships::class)
            ->expectsOutputToContain('Skipped (already exists): 6')
            ->assertSuccessful();

        $this->assertSame(6, DB::table('event_owner_memberships')->count());
        $this->assertSame(6, DB::table('event_user')->count());
    }

    public function test_legacy_event_user_rows_are_not_deleted_and_remain_queryable(): void
    {
        $this->seedLegacyRows(2, 3);
        $before = DB::table('event_user')->count();

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();

        $this->assertSame($before, DB::table('event_user')->count());

        // Every legacy row is still present and queryable by its (event_id, user_id).
        foreach (DB::table('event_user')->get() as $row) {
            $this->assertDatabaseHas('event_user', [
                'event_id' => $row->event_id,
                'user_id' => $row->user_id,
                'role_label' => $row->role_label,
            ]);
        }
    }

    public function test_event_ids_and_user_ids_are_unchanged_after_migration(): void
    {
        $originalRows = $this->seedLegacyRows(2, 2);
        $originalEventIds = array_column($originalRows, 0);
        $originalUserIds = array_column($originalRows, 1);

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();

        $migratedEventIds = DB::table('event_owner_memberships')->pluck('event_id')->all();
        $migratedUserIds = DB::table('event_owner_memberships')->pluck('user_id')->all();

        sort($originalEventIds);
        sort($migratedEventIds);
        sort($originalUserIds);
        sort($migratedUserIds);

        $this->assertSame($originalEventIds, $migratedEventIds);
        $this->assertSame($originalUserIds, $migratedUserIds);
    }

    public function test_migrated_users_retain_event_owner_access(): void
    {
        $service = app(PermissionService::class);
        $rows = $this->seedLegacyRows(2, 2);

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();

        foreach ($rows as [$eventId, $userId, $roleLabel]) {
            $user = User::find($userId);
            $event = Event::find($eventId);

            $this->assertTrue($service->isEventOwner($user, $event));
            $this->assertTrue($user->canAccessEvent($event));
        }
    }

    public function test_no_workspace_owner_or_organization_membership_is_mass_created(): void
    {
        $this->seedLegacyRows(2, 2);
        $before = DB::table('organization_memberships')->count();

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();

        $this->assertSame($before, DB::table('organization_memberships')->count());
        $this->assertSame(0, DB::table('organization_memberships')->count());
    }

    public function test_dry_run_writes_nothing_and_counts_unchanged(): void
    {
        $this->seedLegacyRows(2, 2);
        $beforeUser = DB::table('event_user')->count();
        $beforeOwner = DB::table('event_owner_memberships')->count();

        $this->artisan(MigrateEventUserToOwnerMemberships::class, ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($beforeUser, DB::table('event_user')->count());
        $this->assertSame($beforeOwner, DB::table('event_owner_memberships')->count());
    }

    public function test_unrecognised_role_does_not_corrupt_valid_rows(): void
    {
        // SQLite enforces the CHECK constraint on event_user.role_label, so a
        // direct insert of an invalid value raises a QueryException. We bypass
        // the CHECK constraint for the single bad row only, then restore it, so
        // the command exercises its exception path without touching valid rows.
        // Limitation: on MySQL this would require a different bypass.
        $validRows = $this->seedLegacyRows(2, 2);

        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('event_user')->insert([
            [
                'event_id' => $validRows[0][0],
                'user_id' => User::factory()->create()->id,
                'role_label' => 'ghost',
            ],
        ]);
        DB::statement('PRAGMA ignore_check_constraints = OFF');

        $this->artisan(MigrateEventUserToOwnerMemberships::class)->assertSuccessful();

        // Valid rows migrated, bad row untouched.
        $this->assertSame(count($validRows), DB::table('event_owner_memberships')->count());
        $this->assertSame(count($validRows) + 1, DB::table('event_user')->count());

        // The bad row remains queryable with its original (event_id, user_id).
        $badRow = DB::table('event_user')->where('role_label', 'ghost')->first();
        $this->assertNotNull($badRow);
        $this->assertSame($validRows[0][0], $badRow->event_id);

        // No owner membership was created for the bad row.
        $this->assertDatabaseMissing('event_owner_memberships', [
            'event_id' => $badRow->event_id,
            'user_id' => $badRow->user_id,
        ]);

        // Valid rows still satisfy owner access.
        foreach ($validRows as [$eventId, $userId, $roleLabel]) {
            $user = User::find($userId);
            $event = Event::find($eventId);
            $this->assertTrue($user->canAccessEvent($event));
        }
    }
}
