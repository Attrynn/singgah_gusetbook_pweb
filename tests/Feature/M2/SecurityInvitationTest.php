<?php

namespace Tests\Feature\M2;

use App\Enums\EventOwnerRole;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceInvitationService;
use App\Services\WorkspaceMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class SecurityInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // The register endpoint validates emails with the "dns" rule. Fake the
        // lookup so tests are deterministic and do not depend on real DNS.
        Validator::fakeDnsLookups();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function invitationRoute(string $token): string
    {
        return route('invitations.workspace.accept', ['token' => $token]);
    }

    protected function acceptRoute(string $token): string
    {
        return route('invitations.workspace.accept.store', ['token' => $token]);
    }

    /**
     * Create a user and an OrganizationMembership directly.
     *
     * Note: Organization::users()->attach() is not used because the relation
     * is declared with ->using(OrganizationMembership::class) while
     * OrganizationMembership extends Model (not Pivot), so attach() with pivot
     * attributes throws fromRawAttributes() errors.
     */
    protected function memberOf(Organization $org, OrganizationRole $role): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        return $user;
    }

    protected function attachOwner(Organization $org): User
    {
        return $this->memberOf($org, OrganizationRole::Owner);
    }

    protected function attachMember(Organization $org): User
    {
        return $this->memberOf($org, OrganizationRole::Member);
    }

    /**
     * Extract the token from the newest queued invitation mail for an invitation.
     * The raw token is only ever exposed via the mail (never stored in plaintext).
     */
    protected function issuedToken(WorkspaceInvitation $invitation): string
    {
        $match = collect(Mail::queued(WorkspaceInvitationMail::class))
            ->reverse()
            ->first(fn (WorkspaceInvitationMail $mail): bool => $mail->invitation->id === $invitation->id);

        $this->assertNotNull($match, "No invitation mail was queued for invitation #{$invitation->id}.");

        return $match->rawToken;
    }

    /**
     * Create a staff invitation through the real service and return the
     * invitation plus its raw token (read from the queued mail).
     *
     * @return array{0: WorkspaceInvitation, 1: string}
     */
    protected function makeStaffInvitation(Organization $org, User $inviter, string $email): array
    {
        $invitation = app(WorkspaceInvitationService::class)->createStaffInvitation(
            $org,
            $inviter,
            $email,
            OrganizationRole::Member,
            [],
        );

        return [$invitation, $this->issuedToken($invitation)];
    }

    /**
     * Create a couple invitation through the real service.
     *
     * @return array{0: WorkspaceInvitation, 1: string}
     */
    protected function makeCoupleInvitation(Organization $org, User $inviter, int $eventId, EventOwnerRole $role): array
    {
        $invitation = app(WorkspaceInvitationService::class)->createCoupleInvitation(
            $org,
            $inviter,
            'couple@example.com',
            $eventId,
            $role,
        );

        return [$invitation, $this->issuedToken($invitation)];
    }

    protected function acceptInvitation(string $rawToken, User $user): TestResponse
    {
        return $this->actingAs($user)->post($this->acceptRoute($rawToken));
    }

    protected function members(): WorkspaceMembershipService
    {
        return app(WorkspaceMembershipService::class);
    }

    // -------------------------------------------------------------------------
    // §1 — Replay protection
    // -------------------------------------------------------------------------

    public function test_accepted_token_cannot_be_used_again(): void
    {
        $org = Organization::factory()->create();
        [$invitation, $raw] = $this->makeStaffInvitation($org, $this->attachOwner($org), 'replay@example.com');
        $user = User::factory()->create(['email' => 'replay@example.com']);

        $this->acceptInvitation($raw, $user)->assertStatus(302);

        // Second use of the same accepted token is rejected.
        $this->acceptInvitation($raw, $user)->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // §2 — Expired token
    // -------------------------------------------------------------------------

    public function test_expired_token_is_rejected_on_accept_and_shown_unusable(): void
    {
        $org = Organization::factory()->create();
        [$invitation, $raw] = $this->makeStaffInvitation($org, $this->attachOwner($org), 'expired@example.com');
        $invitation->update(['expires_at' => now()->subHour()]);

        // GET shows an unusable state (200 page, no acceptance form).
        $this->get($this->invitationRoute($raw))->assertOk()->assertSee('kedaluwarsa');

        $user = User::factory()->create(['email' => 'expired@example.com']);
        $this->acceptInvitation($raw, $user)->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // §3 — Revoked token
    // -------------------------------------------------------------------------

    public function test_revoked_token_is_rejected_on_accept_and_shown_unusable(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);
        [$invitation, $raw] = $this->makeStaffInvitation($org, $owner, 'revoked@example.com');

        $this->actingAs($owner)
            ->post(route('workspace.invitations.revoke', [$org, $invitation]))
            ->assertRedirect();

        $this->get($this->invitationRoute($raw))->assertOk();

        $user = User::factory()->create(['email' => 'revoked@example.com']);
        $this->acceptInvitation($raw, $user)->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // §4 — Token reuse after resend
    // -------------------------------------------------------------------------

    public function test_old_token_fails_after_resend_and_new_token_succeeds(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);
        [$invitation, $oldRaw] = $this->makeStaffInvitation($org, $owner, 'resend@example.com');
        $user = User::factory()->create(['email' => 'resend@example.com']);

        $this->actingAs($owner)
            ->post(route('workspace.invitations.resend', [$org, $invitation]))
            ->assertRedirect();

        $newRaw = $this->issuedToken($invitation->fresh());
        $this->assertNotSame($oldRaw, $newRaw);

        // Old token no longer resolves to any invitation.
        $this->acceptInvitation($oldRaw, $user)->assertStatus(404);

        // New token succeeds.
        $this->acceptInvitation($newRaw, $user)->assertStatus(302);
    }

    // -------------------------------------------------------------------------
    // §5 — Email mismatch
    // -------------------------------------------------------------------------

    public function test_user_with_different_email_cannot_accept(): void
    {
        $org = Organization::factory()->create();
        [, $raw] = $this->makeStaffInvitation($org, $this->attachOwner($org), 'target@example.com');
        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);

        $this->acceptInvitation($raw, $wrongUser)->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // §6 — Invitation token enumeration
    // -------------------------------------------------------------------------

    public function test_random_token_returns_404_on_get(): void
    {
        $this->get($this->invitationRoute(Str::random(32)))->assertNotFound();
    }

    public function test_random_token_is_never_accepted_over_post(): void
    {
        $user = User::factory()->create();

        $status = $this->actingAs($user)
            ->post($this->acceptRoute(Str::random(32)))
            ->status();

        $this->assertContains($status, [404, 422]);
    }

    public function test_many_random_tokens_are_not_found_and_leak_nothing(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $token = Str::random(40);
            $response = $this->get($this->invitationRoute($token));

            $response->assertNotFound();
            $this->assertStringNotContainsString('token_hash', $response->getContent());
            $this->assertStringNotContainsString('organization_id', $response->getContent());
        }
    }

    // -------------------------------------------------------------------------
    // §7 — Privilege assignment tampering
    // -------------------------------------------------------------------------

    public function test_non_owner_cannot_create_owner_staff_invitation(): void
    {
        $org = Organization::factory()->create();
        $member = $this->attachMember($org);

        $this->actingAs($member)
            ->post(route('workspace.invitations.store', $org), [
                'email' => 'tamper@example.com',
                'organization_role' => OrganizationRole::Owner->value,
            ])
            ->assertForbidden();
    }

    public function test_acceptance_uses_stored_invitation_role(): void
    {
        $org = Organization::factory()->create();
        [, $raw] = $this->makeStaffInvitation($org, $this->attachOwner($org), 'stored@example.com');
        $user = User::factory()->create(['email' => 'stored@example.com']);

        // The accept endpoint only carries the token; the stored invitation's
        // organization_role is honoured verbatim. There are no role/assignment
        // fields on the request that could override it.
        $this->actingAs($user)
            ->post($this->acceptRoute($raw), ['organization_role' => 'owner', 'role' => 'owner'])
            ->assertStatus(302);

        $membership = OrganizationMembership::where('organization_id', $org->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame(OrganizationRole::Member, $membership->role);
    }

    public function test_couple_acceptance_uses_stored_role_label(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);
        $event = Event::factory()->create(['organization_id' => $org->id]);
        [, $raw] = $this->makeCoupleInvitation($org, $owner, $event->id, EventOwnerRole::Bride);
        $user = User::factory()->create(['email' => 'couple@example.com']);

        $this->actingAs($user)
            ->post($this->acceptRoute($raw), ['role_label' => 'groom'])
            ->assertStatus(302);

        $this->assertDatabaseHas('event_owner_memberships', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role_label' => EventOwnerRole::Bride->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // §8 — Cross-organization IDOR
    // -------------------------------------------------------------------------

    public function test_updating_nonexistent_member_in_org_a_is_rejected_with_422(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $owner = $this->attachOwner($orgA);
        $target = $this->attachMember($orgB);

        $this->actingAs($owner)
            ->put(route('workspace.members.update', [$orgA, $target]), ['role' => OrganizationRole::Member->value])
            ->assertStatus(422);
    }

    public function test_destroying_nonexistent_member_in_org_a_is_rejected_with_422(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $admin = $this->memberOf($orgA, OrganizationRole::Admin);
        $target = $this->attachMember($orgB);

        $this->actingAs($admin)
            ->delete(route('workspace.members.destroy', [$orgA, $target]))
            ->assertStatus(422);
    }

    public function test_revoking_invitation_in_org_b_while_acting_in_org_a_is_404(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $ownerA = $this->attachOwner($orgA);
        $invitation = WorkspaceInvitation::factory()->create([
            'organization_id' => $orgB->id,
            'type' => 'staff',
            'status' => 'pending',
        ]);

        $this->actingAs($ownerA)
            ->post(route('workspace.invitations.revoke', [$orgA, $invitation]))
            ->assertStatus(404);
    }

    public function test_couple_invitation_for_event_in_other_org_is_404(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $ownerA = $this->attachOwner($orgA);
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($ownerA)
            ->post(route('workspace.couple-invitations.store', $orgA), [
                'email' => 'couple@example.com',
                'event_id' => $eventB->id,
                'role_label' => EventOwnerRole::Groom->value,
            ])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // §9 — Last-owner protection
    // -------------------------------------------------------------------------

    public function test_demoting_sole_active_owner_is_rejected_with_422(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);

        $this->actingAs($owner)
            ->put(route('workspace.members.update', [$org, $owner]), ['role' => OrganizationRole::Member->value])
            ->assertStatus(422);
    }

    public function test_destroying_sole_active_owner_is_rejected_with_422(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);

        $this->actingAs($owner)
            ->delete(route('workspace.members.destroy', [$org, $owner]))
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // §10 — Membership lifecycle
    // -------------------------------------------------------------------------

    /*
     * A plain Member cannot reach the members.index controller (that requires
     * manageMembers), so workspace access for lifecycle checks is probed with
     * workspace.switch, which is gated only by the org.member middleware.
     */
    public function test_suspended_then_revived_member_regains_access(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);
        $member = $this->attachMember($org);

        // Active: gated route is reachable.
        $this->actingAs($member)->post(route('workspace.switch', $org))->assertRedirect();

        $this->members()->suspend($org, $member, $owner);
        $this->actingAs($member)->post(route('workspace.switch', $org))->assertForbidden();

        $this->members()->restore($org, $member, $owner);
        $this->actingAs($member)->post(route('workspace.switch', $org))->assertRedirect();
    }

    public function test_revoked_member_stays_denied(): void
    {
        $org = Organization::factory()->create();
        $owner = $this->attachOwner($org);
        $member = $this->attachMember($org);

        $this->actingAs($member)->post(route('workspace.switch', $org))->assertRedirect();

        $this->members()->revoke($org, $member, $owner);
        $this->actingAs($member)->post(route('workspace.switch', $org))->assertForbidden();
        $this->actingAs($member)->post(route('workspace.switch', $org))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // §11 — Context / session isolation
    // -------------------------------------------------------------------------

    public function test_switching_context_does_not_grant_access_to_other_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $ownerA = $this->attachOwner($orgA); // member of A only

        // Switching to an org the user is not a member of is denied by the
        // org.member middleware, so no context is granted for B.
        $this->actingAs($ownerA)
            ->post(route('workspace.switch', $orgB))
            ->assertForbidden();

        // Original context (A) remains accessible.
        $this->actingAs($ownerA)->get(route('workspace.members.index', $orgA))->assertOk();

        // Even if switching were allowed, B would still be denied — switching
        // does not grant membership in another organization.
        $this->actingAs($ownerA)->get(route('workspace.members.index', $orgB))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // §12 — Registration attack surface
    // -------------------------------------------------------------------------

    public function test_register_store_cannot_elevate_platform_role_or_role(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'platform@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'workspace_name' => 'Platform Org',
            'platform_role' => 'superadmin',
            'role' => 'superadmin',
            'organization_id' => 999,
        ]);

        $response->assertStatus(302);

        $user = User::where('email', 'platform@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->platform_role);
        $this->assertSame(UserRole::Owner, $user->role);
    }

    public function test_duplicate_email_is_rejected_on_register(): void
    {
        // An account already exists for this email (must be unauthenticated to
        // pass the guest middleware on the register route).
        User::factory()->create(['email' => 'dup@example.com']);

        // Form validation failures redirect back (302) with errors, and must
        // not create a second account for the duplicate email.
        $this->post(route('register.store'), [
            'name' => 'Second',
            'email' => 'dup@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'workspace_name' => 'Second Org',
        ])->assertStatus(302)->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
        $this->assertGuest();
    }
}
