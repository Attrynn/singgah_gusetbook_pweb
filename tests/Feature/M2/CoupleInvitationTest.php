<?php

namespace Tests\Feature\M2;

use App\Enums\EventOwnerRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CoupleInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_owner_can_invite_couple_for_each_role(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        foreach (EventOwnerRole::cases() as $role) {
            $response = $this->actingAs($owner)->post(route('workspace.couple-invitations.store', $org), [
                'email' => "couple-{$role->value}@example.com",
                'event_id' => $event->id,
                'role_label' => $role->value,
            ]);

            $response->assertStatus(302);
        }

        $this->assertSame(3, WorkspaceInvitation::count());
        $this->assertSame(0, WorkspaceInvitation::where('type', '!=', 'couple')->count());
        $this->assertSame(0, WorkspaceInvitation::where('status', '!=', 'pending')->count());

        Mail::assertQueued(WorkspaceInvitationMail::class, 3);
    }

    public function test_accepted_couple_invitation_creates_owner_membership_with_matching_role(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        foreach (EventOwnerRole::cases() as $role) {
            $email = "couple-{$role->value}@example.com";
            $raw = "known-couple-token-{$role->value}";
            $this->makeCoupleInvitation($org, $owner, $email, $raw, $event, $role);

            $user = User::factory()->create(['email' => $email]);

            $this->actingAs($user)
                ->post(route('invitations.workspace.accept.store', $raw))
                ->assertStatus(302);

            $membership = EventOwnerMembership::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            $this->assertNotNull($membership);
            $this->assertSame($role, $membership->role_label);
            $this->assertTrue($membership->isActive());
        }
    }

    public function test_single_user_can_be_couple_owner_on_multiple_events(): void
    {
        $org = $this->makeOrganization();
        $eventOne = $this->makeEvent($org);
        $eventTwo = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $user = User::factory()->create(['email' => 'multi@example.com']);

        $rawOne = 'known-couple-token-multi-one';
        $rawTwo = 'known-couple-token-multi-two';
        $this->makeCoupleInvitation($org, $owner, 'multi@example.com', $rawOne, $eventOne, EventOwnerRole::Groom);
        $this->makeCoupleInvitation($org, $owner, 'multi@example.com', $rawTwo, $eventTwo, EventOwnerRole::Groom);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $rawOne))
            ->assertStatus(302);
        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $rawTwo))
            ->assertStatus(302);

        $this->assertSame(1, User::where('email', 'multi@example.com')->count());
        $this->assertSame(2, $user->eventOwnerMemberships()->count());
        $this->assertTrue($eventOne->hasOwnerMember($user));
        $this->assertTrue($eventTwo->hasOwnerMember($user));
    }

    public function test_existing_account_is_reused_when_email_matches(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $raw = 'known-couple-token-existing';
        $this->makeCoupleInvitation($org, $owner, 'existing@example.com', $raw, $event, EventOwnerRole::Bride);

        $this->actingAs($existing)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(302);

        $this->assertSame(2, User::count());
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
        $this->assertSame(1, EventOwnerMembership::where('user_id', $existing->id)->count());
    }

    public function test_email_mismatch_returns_forbidden(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-couple-token-mismatch';
        $invitation = $this->makeCoupleInvitation(
            $org,
            $owner,
            'invited@example.com',
            $raw,
            $event,
            EventOwnerRole::Groom,
        );

        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($other)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(403);

        $this->assertSame('pending', $invitation->fresh()->status->value);
    }

    public function test_couple_invitation_is_bound_to_its_specified_event_only(): void
    {
        $org = $this->makeOrganization();
        $eventOne = $this->makeEvent($org);
        $eventTwo = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $user = User::factory()->create(['email' => 'bound@example.com']);

        $raw = 'known-couple-token-bound';
        $this->makeCoupleInvitation($org, $owner, 'bound@example.com', $raw, $eventOne, EventOwnerRole::Partner);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(302);

        $this->assertDatabaseHas('event_owner_memberships', [
            'event_id' => $eventOne->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('event_owner_memberships', [
            'event_id' => $eventTwo->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_couple_acceptance_creates_no_org_membership_or_staff_membership(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $user = User::factory()->create(['email' => 'owner-only@example.com']);

        $raw = 'known-couple-token-owner-only';
        $this->makeCoupleInvitation($org, $owner, 'owner-only@example.com', $raw, $event, EventOwnerRole::Groom);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(302);

        $this->assertNull($org->membershipFor($user));
        $this->assertSame(0, OrganizationMembership::where('user_id', $user->id)->count());
        $this->assertSame(0, $user->eventStaffMemberships()->count());
    }

    public function test_revoked_couple_invitation_cannot_be_accepted(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $user = User::factory()->create(['email' => 'revoked-couple@example.com']);

        $raw = 'known-couple-token-revoked';
        $invitation = $this->makeCoupleInvitation($org, $owner, 'revoked-couple@example.com', $raw, $event, EventOwnerRole::Bride);
        $invitation->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(422);

        $this->assertDatabaseMissing('event_owner_memberships', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_couple_invitation_for_event_in_another_organization_returns_not_found(): void
    {
        $org = $this->makeOrganization();
        $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $foreignOrg = $this->makeOrganization('Org B');
        $foreignEvent = $this->makeEvent($foreignOrg);

        $response = $this->actingAs($owner)->post(route('workspace.couple-invitations.store', $org), [
            'email' => 'couple@example.com',
            'event_id' => $foreignEvent->id,
            'role_label' => EventOwnerRole::Groom->value,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    private function makeOrganization(string $name = 'Org A'): Organization
    {
        return Organization::factory()->create(['name' => $name]);
    }

    private function makeEvent(Organization $org): Event
    {
        return Event::factory()->create(['organization_id' => $org->id]);
    }

    private function makeOwner(Organization $org): User
    {
        $user = User::factory()->create();
        OrganizationMembership::factory()->owner()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    /**
     * Create a pending couple invitation with a known raw token so acceptance
     * can be driven deterministically without decoding the stored hash.
     */
    private function makeCoupleInvitation(
        Organization $org,
        User $inviter,
        string $email,
        string $rawToken,
        Event $event,
        EventOwnerRole $role,
        ?\DateTimeInterface $expiresAt = null,
    ): WorkspaceInvitation {
        return WorkspaceInvitation::create([
            'organization_id' => $org->id,
            'invited_by' => $inviter->id,
            'email' => $email,
            'type' => 'couple',
            'organization_role' => null,
            'event_assignments' => [['event_id' => $event->id, 'role_label' => $role->value]],
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'pending',
            'expires_at' => $expiresAt ?? now()->addDays(30),
        ]);
    }
}
