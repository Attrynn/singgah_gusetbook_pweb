<?php

namespace Tests\Feature\M2;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_authorized_owner_can_invite_staff_and_mail_is_queued(): void
    {
        $org = $this->makeOrganization();
        $eventOne = $this->makeEvent($org);
        $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $response = $this->actingAs($owner)->post(route('workspace.invitations.store', $org), [
            'email' => 'staff@example.com',
            'organization_role' => OrganizationRole::Member->value,
            'assignments' => [
                ['event_id' => $eventOne->id, 'role' => EventStaffRole::GuestManager->value],
            ],
        ]);

        $response->assertStatus(302);

        $invitation = WorkspaceInvitation::first();
        $this->assertNotNull($invitation);
        $this->assertSame('staff', $invitation->type->value);
        $this->assertSame('pending', $invitation->status->value);
        // Stored role must not exceed the inviter's (Owner) authority.
        $this->assertSame('member', $invitation->organization_role);

        Mail::assertQueued(WorkspaceInvitationMail::class);
    }

    public function test_basic_member_cannot_invite(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $member = $this->makeMember($org, OrganizationRole::Member);

        $response = $this->actingAs($member)->post(route('workspace.invitations.store', $org), [
            'email' => 'staff@example.com',
            'organization_role' => OrganizationRole::Member->value,
            'assignments' => [
                ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_admin_cannot_grant_owner_role(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $admin = $this->makeMember($org, OrganizationRole::Admin);

        $response = $this->actingAs($admin)->post(route('workspace.invitations.store', $org), [
            'email' => 'staff@example.com',
            'organization_role' => OrganizationRole::Owner->value,
            'assignments' => [
                ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_event_assignment_outside_organization_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $this->makeEvent($org);
        $foreignOrg = $this->makeOrganization('Org B');
        $foreignEvent = $this->makeEvent($foreignOrg);
        $owner = $this->makeOwner($org);

        $response = $this->actingAs($owner)->post(route('workspace.invitations.store', $org), [
            'email' => 'staff@example.com',
            'organization_role' => OrganizationRole::Member->value,
            'assignments' => [
                ['event_id' => $foreignEvent->id, 'role' => EventStaffRole::GuestManager->value],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_existing_account_accepts_invitation_and_reuses_user(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-existing';
        $invitation = $this->makeStaffInvitation($org, $owner, 'staff@example.com', $raw, [
            ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
        ]);

        $staff = User::factory()->create(['email' => 'staff@example.com']);

        $this->actingAs($staff)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(302);

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status->value);
        $this->assertSame($staff->id, $invitation->accepted_by);

        // No second user account was created for the invited email.
        $this->assertSame(2, User::count());
        $this->assertSame(1, User::where('email', 'staff@example.com')->count());

        $membership = OrganizationMembership::where('organization_id', $org->id)
            ->where('user_id', $staff->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertTrue($membership->isActive());
        $this->assertSame(OrganizationRole::Member, $membership->role);

        $staffMembership = EventStaffMembership::where('event_id', $event->id)
            ->where('user_id', $staff->id)
            ->where('role', EventStaffRole::GuestManager->value)
            ->first();
        $this->assertNotNull($staffMembership);
        $this->assertTrue($staffMembership->isActive());
    }

    public function test_new_account_registers_via_invitation_and_ignores_posted_email(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-register';
        $invitation = $this->makeStaffInvitation($org, $owner, 'newstaff@example.com', $raw, [
            ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
        ]);

        $this->post(route('invitations.workspace.accept.register', $raw), [
            'name' => 'New Staff',
            'email' => 'attacker@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
        ])->assertStatus(302);

        $newUser = User::where('email', 'newstaff@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertNull(User::where('email', 'attacker@example.com')->first());

        // No new organization is created by accepting a staff invitation.
        $this->assertSame(1, Organization::count());

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status->value);
        $this->assertAuthenticatedAs($newUser);

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $newUser->id,
            'role' => OrganizationRole::Member->value,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('event_staff_memberships', [
            'event_id' => $event->id,
            'user_id' => $newUser->id,
            'role' => EventStaffRole::GuestManager->value,
            'status' => 'active',
        ]);
    }

    public function test_email_mismatch_returns_forbidden_and_leaves_invitation_pending(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-mismatch';
        $invitation = $this->makeStaffInvitation($org, $owner, 'invited@example.com', $raw, [
            ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
        ]);

        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($other)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(403);

        $this->assertSame('pending', $invitation->fresh()->status->value);
    }

    public function test_expired_token_is_rejected_and_show_marks_unusable(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-expired';
        $this->makeStaffInvitation($org, $owner, 'expired@example.com', $raw, [
            ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
        ], expiresAt: now()->subDay());

        $user = User::factory()->create(['email' => 'expired@example.com']);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(422);

        $this->get(route('invitations.workspace.accept', $raw))
            ->assertOk()
            ->assertViewHas('state', 'unusable');
    }

    public function test_revoked_token_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-revoked';
        $invitation = $this->makeStaffInvitation($org, $owner, 'revoked@example.com', $raw, [
            ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
        ]);
        $invitation->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $user = User::factory()->create(['email' => 'revoked@example.com']);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(422);
    }

    public function test_already_accepted_token_cannot_be_reused(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-reuse';
        $this->makeStaffInvitation($org, $owner, 'reuse@example.com', $raw, [
            ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
        ]);

        $user = User::factory()->create(['email' => 'reuse@example.com']);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(302);

        $this->actingAs($user)
            ->post(route('invitations.workspace.accept.store', $raw))
            ->assertStatus(422);
    }

    public function test_role_tampering_on_accept_is_ignored(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $owner = $this->makeOwner($org);

        $raw = 'known-staff-token-tamper';
        $invitation = $this->makeStaffInvitation(
            $org,
            $owner,
            'tamper@example.com',
            $raw,
            [['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value]],
            OrganizationRole::Member->value,
        );

        $user = User::factory()->create(['email' => 'tamper@example.com']);

        $this->actingAs($user)->post(route('invitations.workspace.accept.store', $raw), [
            'organization_role' => OrganizationRole::Owner->value,
            'assignments' => [
                ['event_id' => $event->id, 'role' => EventStaffRole::SeatingUsher->value],
            ],
        ])->assertStatus(302);

        $membership = OrganizationMembership::where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertSame(OrganizationRole::Member, $membership->role);

        $staffMembership = EventStaffMembership::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertSame(EventStaffRole::GuestManager, $staffMembership->role);
    }

    public function test_non_member_cannot_invite_to_organization(): void
    {
        $org = $this->makeOrganization();
        $event = $this->makeEvent($org);
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->post(route('workspace.invitations.store', $org), [
            'email' => 'staff@example.com',
            'organization_role' => OrganizationRole::Member->value,
            'assignments' => [
                ['event_id' => $event->id, 'role' => EventStaffRole::GuestManager->value],
            ],
        ]);

        $response->assertStatus(403);
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

    private function makeMember(Organization $org, OrganizationRole $role): User
    {
        $user = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
        ]);

        return $user;
    }

    /**
     * Create a pending staff invitation with a known raw token so acceptance can
     * be driven without decoding the hashed token stored on the model.
     *
     * @param  array<int, array{event_id: int, role: string}>  $assignments
     */
    private function makeStaffInvitation(
        Organization $org,
        User $inviter,
        string $email,
        string $rawToken,
        array $assignments,
        string $organizationRole = 'member',
        ?\DateTimeInterface $expiresAt = null,
    ): WorkspaceInvitation {
        return WorkspaceInvitation::create([
            'organization_id' => $org->id,
            'invited_by' => $inviter->id,
            'email' => $email,
            'type' => 'staff',
            'organization_role' => $organizationRole,
            'event_assignments' => $assignments,
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'pending',
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }
}
