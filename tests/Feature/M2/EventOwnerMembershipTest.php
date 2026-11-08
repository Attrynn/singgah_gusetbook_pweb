<?php

namespace Tests\Feature\M2;

use App\Authorization\PermissionRegistry;
use App\Enums\EventOwnerRole;
use App\Enums\EventUserRole;
use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOwnerMembershipTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = app(PermissionService::class);
    }

    private function own(User $user, Event $event, EventOwnerRole $role = EventOwnerRole::Bride): EventOwnerMembership
    {
        return EventOwnerMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role_label' => $role->value,
        ]);
    }

    public function test_event_owner_can_access_their_own_event(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->own($user, $event, EventOwnerRole::Groom);

        $this->assertTrue($this->permissions->isEventOwner($user, $event));
        $this->assertTrue($user->canAccessEvent($event));
    }

    public function test_event_owner_cannot_access_a_different_event(): void
    {
        $org = Organization::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $org->id]);
        $event2 = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->own($user, $event1, EventOwnerRole::Bride);

        $this->assertFalse($this->permissions->isEventOwner($user, $event2));
        $this->assertFalse($user->canAccessEvent($event2));
    }

    public function test_owner_role_mapping_and_factory_states(): void
    {
        $this->assertSame(EventOwnerRole::Groom, EventOwnerRole::from(EventUserRole::Groom->value));
        $this->assertSame(EventOwnerRole::Bride, EventOwnerRole::from(EventUserRole::Bride->value));
        $this->assertSame(EventOwnerRole::Partner, EventOwnerRole::from(EventUserRole::Partner->value));

        $this->assertSame(EventOwnerRole::Groom, EventOwnerRole::fromEventUserRole(EventUserRole::Groom));
        $this->assertSame(EventOwnerRole::Bride, EventOwnerRole::fromEventUserRole(EventUserRole::Bride));
        $this->assertSame(EventOwnerRole::Partner, EventOwnerRole::fromEventUserRole(EventUserRole::Partner));

        $groom = EventOwnerMembership::factory()->groom()->create();
        $bride = EventOwnerMembership::factory()->bride()->create();
        $partner = EventOwnerMembership::factory()->partner()->create();

        $this->assertSame(EventOwnerRole::Groom, $groom->role_label);
        $this->assertSame(EventOwnerRole::Bride, $bride->role_label);
        $this->assertSame(EventOwnerRole::Partner, $partner->role_label);
        $this->assertSame('Groom', $groom->role_label->label());
        $this->assertSame('Bride', $bride->role_label->label());
        $this->assertSame('Partner', $partner->role_label->label());
    }

    public function test_event_owner_is_not_automatically_a_workspace_member(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->own($user, $event, EventOwnerRole::Bride);

        $this->assertNull($user->organizationMembershipFor($org));
    }

    public function test_event_owner_is_not_automatically_event_staff(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->own($user, $event, EventOwnerRole::Groom);

        $this->assertFalse(
            $this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_CREATE),
        );
    }

    public function test_one_account_can_own_multiple_events(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $orgA->id]);
        $event2 = Event::factory()->create(['organization_id' => $orgB->id]);
        $user = User::factory()->create();
        $this->own($user, $event1, EventOwnerRole::Groom);
        $this->own($user, $event2, EventOwnerRole::Groom);

        $this->assertSame(2, $user->eventOwnerMemberships()->count());
        $this->assertTrue($this->permissions->isEventOwner($user, $event1));
        $this->assertTrue($this->permissions->isEventOwner($user, $event2));
    }

    public function test_revocation_cuts_access(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $owner = $this->own($user, $event, EventOwnerRole::Bride);

        $this->assertTrue($user->canAccessEvent($event));

        $owner->update(['status' => 'revoked']);

        $this->assertFalse($this->permissions->isEventOwner($user, $event));
        $this->assertFalse($user->canAccessEvent($event));
    }
}
