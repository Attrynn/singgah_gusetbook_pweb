<?php

namespace Tests\Feature\M3;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\RunOfShowStatus;
use App\Models\Event;
use App\Models\EventRunOfShowItem;
use App\Models\EventSession;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RunOfShowTest extends TestCase
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

    protected function staffMember(Organization $org, Event $event, EventStaffRole $role): User
    {
        $user = $this->memberOf($org, OrganizationRole::Member->value);

        EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => MembershipStatus::Active->value,
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

    // -------------------------------------------------------------------------
    // View authorization
    // -------------------------------------------------------------------------

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $org = $this->organization('ros-view');
        $actor = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        $this->actingAs($actor)
            ->get(route('workspace.events.run-of-show.index', [$org, $event]))
            ->assertForbidden();
    }

    public function test_guest_manager_can_view_but_not_manage(): void
    {
        $org = $this->organization('ros-guest-manager');
        $event = $this->eventIn($org);
        $actor = $this->staffMember($org, $event, EventStaffRole::GuestManager);
        $item = EventRunOfShowItem::factory()->create(['event_id' => $event->id]);

        $this->actingAs($actor)
            ->get(route('workspace.events.run-of-show.index', [$org, $event]))
            ->assertOk();

        $this->actingAs($actor)
            ->put(route('workspace.events.run-of-show.update', [$org, $event, $item]), [
                'title' => 'Diubah',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->delete(route('workspace.events.run-of-show.destroy', [$org, $event, $item]))
            ->assertForbidden();
    }

    public function test_event_manager_can_view_and_manage(): void
    {
        $org = $this->organization('ros-event-manager');
        $event = $this->eventIn($org);
        $actor = $this->staffMember($org, $event, EventStaffRole::EventManager);

        $this->actingAs($actor)
            ->get(route('workspace.events.run-of-show.index', [$org, $event]))
            ->assertOk();

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $event]), [
                'title' => 'Kirab Pengantin',
            ])
            ->assertRedirect();

        $item = EventRunOfShowItem::query()->where('event_id', $event->id)->firstOrFail();

        $this->actingAs($actor)
            ->put(route('workspace.events.run-of-show.update', [$org, $event, $item]), [
                'title' => 'Kirab Pengantin Diperbarui',
            ])
            ->assertRedirect();

        $this->assertSame('Kirab Pengantin Diperbarui', $item->fresh()->title);

        $this->actingAs($actor)
            ->delete(route('workspace.events.run-of-show.destroy', [$org, $event, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('event_run_of_show_items', ['id' => $item->id]);
    }

    // -------------------------------------------------------------------------
    // Event isolation
    // -------------------------------------------------------------------------

    public function test_item_of_another_event_cannot_be_updated_or_deleted(): void
    {
        $org = $this->organization('ros-idor');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $itemA = EventRunOfShowItem::factory()->create([
            'event_id' => $eventA->id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->put(route('workspace.events.run-of-show.update', [$org, $eventB, $itemA]), [
                'title' => 'Hijacked',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('workspace.events.run-of-show.destroy', [$org, $eventB, $itemA]))
            ->assertNotFound();

        $this->assertDatabaseHas('event_run_of_show_items', ['id' => $itemA->id]);
    }

    // -------------------------------------------------------------------------
    // Timezone conversion + multi-day
    // -------------------------------------------------------------------------

    public function test_create_converts_local_times_to_utc(): void
    {
        $org = $this->organization('ros-utc');
        $event = $this->eventIn($org, ['timezone' => 'Asia/Jakarta']);
        $actor = $this->staffMember($org, $event, EventStaffRole::EventManager);

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $event]), [
                'title' => 'Kirab Pengantin',
                'planned_start_local' => '2026-12-25 10:00',
                'planned_end_local' => '2026-12-25 11:00',
                'actual_start_local' => '2026-12-25 10:30',
                'actual_end_local' => '2026-12-25 11:30',
            ])
            ->assertRedirect();

        $item = EventRunOfShowItem::query()->where('event_id', $event->id)->firstOrFail();

        // Asia/Jakarta is UTC+7, so local wall-clock times shift back seven hours.
        $this->assertSame('2026-12-25 03:00:00', $item->planned_start?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-25 04:00:00', $item->planned_end?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-25 03:30:00', $item->actual_start?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-25 04:30:00', $item->actual_end?->format('Y-m-d H:i:s'));
    }

    public function test_item_can_be_linked_to_a_session_on_a_different_date(): void
    {
        $org = $this->organization('ros-multiday');
        $event = $this->eventIn($org);
        $actor = $this->staffMember($org, $event, EventStaffRole::EventManager);

        EventSession::factory()->create([
            'event_id' => $event->id,
            'name' => 'Hari Pertama',
            'session_date' => '2026-12-25',
        ]);
        $secondDay = EventSession::factory()->create([
            'event_id' => $event->id,
            'name' => 'Hari Kedua',
            'session_date' => '2026-12-26',
        ]);

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $event]), [
                'title' => 'Resepsi Hari Kedua',
                'session_id' => $secondDay->id,
            ])
            ->assertRedirect();

        $item = EventRunOfShowItem::query()->where('event_id', $event->id)->firstOrFail();

        $this->assertSame($secondDay->id, $item->event_session_id);
        $this->assertSame('2026-12-26', $item->session?->session_date?->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // PIC validation
    // -------------------------------------------------------------------------

    public function test_pic_validation_rejects_inactive_or_foreign_membership(): void
    {
        $org = $this->organization('ros-pic');
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $actor = $this->staffMember($org, $eventA, EventStaffRole::EventManager);

        $revoked = EventStaffMembership::factory()->revoked()->create([
            'event_id' => $eventA->id,
            'user_id' => User::factory()->create(['is_active' => true])->id,
        ]);

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $eventA]), [
                'title' => 'Item',
                'pic_staff_membership_id' => $revoked->id,
            ])
            ->assertStatus(422);

        $foreign = EventStaffMembership::factory()->create([
            'event_id' => $eventB->id,
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'status' => MembershipStatus::Active->value,
        ]);

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $eventA]), [
                'title' => 'Item',
                'pic_staff_membership_id' => $foreign->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, EventRunOfShowItem::query()->where('event_id', $eventA->id)->count());
    }

    // -------------------------------------------------------------------------
    // Status + internal separation
    // -------------------------------------------------------------------------

    public function test_status_is_stored_via_run_of_show_status_enum(): void
    {
        $org = $this->organization('ros-status');
        $event = $this->eventIn($org);
        $actor = $this->staffMember($org, $event, EventStaffRole::EventManager);

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $event]), [
                'title' => 'Kirab Pengantin',
                'status' => RunOfShowStatus::InProgress->value,
            ])
            ->assertRedirect();

        $item = EventRunOfShowItem::query()->where('event_id', $event->id)->firstOrFail();

        $this->assertSame(RunOfShowStatus::InProgress, $item->status);
        $this->assertDatabaseHas('event_run_of_show_items', [
            'id' => $item->id,
            'status' => RunOfShowStatus::InProgress->value,
        ]);

        $this->actingAs($actor)
            ->put(route('workspace.events.run-of-show.update', [$org, $event, $item]), [
                'title' => 'Kirab Pengantin',
                'status' => RunOfShowStatus::Completed->value,
            ])
            ->assertRedirect();

        $this->assertSame(RunOfShowStatus::Completed, $item->fresh()->status);

        $this->actingAs($actor)
            ->post(route('workspace.events.run-of-show.store', [$org, $event]), [
                'title' => 'Item',
                'status' => 'nonsense',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_internal_notes_are_not_exposed_in_public_schedule_response(): void
    {
        $org = $this->organization('ros-internal');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $event = $this->eventIn($org);

        EventRunOfShowItem::factory()->create([
            'event_id' => $event->id,
            'title' => 'Kirab Pengantin',
            'internal_notes' => 'SECRET_INTERNAL_ROS_NOTE',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.events.schedule.index', [$org, $event]))
            ->assertOk()
            ->assertDontSee('SECRET_INTERNAL_ROS_NOTE');
    }
}
