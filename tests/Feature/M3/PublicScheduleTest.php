<?php

namespace Tests\Feature\M3;

use App\Enums\EventStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\ScheduleStatus;
use App\Models\Event;
use App\Models\EventRunOfShowItem;
use App\Models\EventSchedule;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function organization(string $slug): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    protected function memberOf(Organization $org, string $role = 'member'): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMembershipStatus::Active->value,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    protected function eventIn(Organization $org, array $attributes = []): Event
    {
        return Event::factory()->create(array_merge([
            'organization_id' => $org->id,
            'timezone' => 'Asia/Jakarta',
        ], $attributes));
    }

    protected function scheduleFor(Event $event, array $attributes = []): EventSchedule
    {
        return EventSchedule::create(array_merge([
            'event_id' => $event->id,
            'title' => 'Prosesi Akad',
            'description' => 'Upacara sakral',
            'start_time' => '2026-12-25 03:00:00',
            'end_time' => '2026-12-25 04:00:00',
            'status' => ScheduleStatus::Planned->value,
            'sort_order' => 0,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // Editing the public schedule
    // -------------------------------------------------------------------------

    public function test_authorized_manager_can_create_public_schedule(): void
    {
        $org = $this->organization('sched-create');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $event = $this->eventIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.schedule.store', [$org, $event]), [
                'title' => 'Prosesi Akad',
                'description' => 'Upacara sakral',
                'start_time' => '2026-12-25 10:00',
                'end_time' => '2026-12-25 11:00',
                'status' => ScheduleStatus::Planned->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_schedules', [
            'event_id' => $event->id,
            'title' => 'Prosesi Akad',
        ]);
    }

    public function test_stored_datetimes_are_converted_to_utc_from_event_timezone(): void
    {
        $org = $this->organization('sched-utc');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $event = $this->eventIn($org, ['timezone' => 'Asia/Jakarta']);

        $this->actingAs($owner)
            ->post(route('workspace.events.schedule.store', [$org, $event]), [
                'title' => 'Prosesi Akad',
                'start_time' => '2026-12-25 10:00',
                'end_time' => '2026-12-25 11:00',
            ])
            ->assertRedirect();

        $schedule = EventSchedule::query()->where('event_id', $event->id)->firstOrFail();

        // 10:00 / 11:00 in Asia/Jakarta (UTC+7) is 03:00 / 04:00 UTC.
        $this->assertSame('2026-12-25 03:00:00', $schedule->start_time?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-25 04:00:00', $schedule->end_time?->format('Y-m-d H:i:s'));
    }

    public function test_member_without_schedule_permission_cannot_create_schedule(): void
    {
        $org = $this->organization('sched-forbidden');
        $actor = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        $this->actingAs($actor)
            ->post(route('workspace.events.schedule.store', [$org, $event]), [
                'title' => 'Prosesi Akad',
                'start_time' => '2026-12-25 10:00',
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Tenant / event isolation
    // -------------------------------------------------------------------------

    public function test_schedule_of_another_event_cannot_be_updated_or_deleted(): void
    {
        $org = $this->organization('sched-idor');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $scheduleB = $this->scheduleFor($eventB);

        $this->actingAs($owner)
            ->put(route('workspace.events.schedule.update', [$org, $eventA, $scheduleB]), [
                'title' => 'Hijacked',
                'start_time' => '2026-12-25 10:00',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('workspace.events.schedule.destroy', [$org, $eventA, $scheduleB]))
            ->assertNotFound();

        $this->assertDatabaseHas('event_schedules', [
            'id' => $scheduleB->id,
            'title' => 'Prosesi Akad',
        ]);
    }

    public function test_schedule_of_another_tenant_is_not_accessible(): void
    {
        $orgA = $this->organization('sched-tenant-a');
        $orgB = $this->organization('sched-tenant-b');
        $ownerA = $this->memberOf($orgA, OrganizationRole::Owner->value);
        $eventB = $this->eventIn($orgB);
        $scheduleB = $this->scheduleFor($eventB);

        // The actor is not a member of orgB, so the workspace gate denies access.
        $this->actingAs($ownerA)
            ->get(route('workspace.events.schedule.index', [$orgB, $eventB]))
            ->assertForbidden();

        // Even through their own workspace, another tenant's schedule is a 404.
        $eventA = $this->eventIn($orgA);

        $this->actingAs($ownerA)
            ->put(route('workspace.events.schedule.update', [$orgA, $eventA, $scheduleB]), [
                'title' => 'Hijacked',
                'start_time' => '2026-12-25 10:00',
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Public / internal separation
    // -------------------------------------------------------------------------

    public function test_public_landing_renders_schedule_with_only_public_fields(): void
    {
        $org = $this->organization('sched-public');
        $event = $this->eventIn($org, ['status' => EventStatus::Published]);
        $this->scheduleFor($event, ['title' => 'Prosesi Akad Nikah']);

        $staffUser = User::factory()->create([
            'name' => 'SecretPicName',
            'email' => 'secret.pic@example.com',
        ]);
        $staff = EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $staffUser->id,
            'role' => 'event_manager',
            'status' => MembershipStatus::Active->value,
        ]);

        EventRunOfShowItem::factory()->create([
            'event_id' => $event->id,
            'title' => 'SECRET_RUN_OF_SHOW_TITLE',
            'internal_description' => 'SECRET_INTERNAL_DESCRIPTION',
            'internal_notes' => 'SECRET_INTERNAL_NOTE',
            'pic_staff_membership_id' => $staff->id,
        ]);

        $response = $this->get(route('guest.landing', $event->slug));

        $response->assertOk();
        $response->assertSee('Prosesi Akad Nikah');
        $response->assertDontSee('SECRET_RUN_OF_SHOW_TITLE');
        $response->assertDontSee('SECRET_INTERNAL_DESCRIPTION');
        $response->assertDontSee('SECRET_INTERNAL_NOTE');
        $response->assertDontSee('SecretPicName');
        $response->assertDontSee('secret.pic@example.com');
    }
}
