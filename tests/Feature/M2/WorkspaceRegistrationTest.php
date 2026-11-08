<?php

namespace Tests\Feature\M2;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class WorkspaceRegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Make the `email:rfc,dns` validation rule deterministic regardless of
        // network availability in the sandbox.
        Validator::fakeDnsLookups();
        Mail::fake();
    }

    public function test_registering_creates_user_organization_and_membership_atomically(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Budi & Ani',
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('organization_memberships', 1);

        $user = User::first();
        $org = Organization::first();

        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertNull($user->platform_role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->isWorkspaceOwner($org));

        $this->assertSame('Budi & Ani', $org->name);
        $this->assertSame('budi-ani', $org->slug);
        $this->assertTrue($org->isActive());

        $membership = OrganizationMembership::where('organization_id', $org->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame(OrganizationRole::Owner, $membership->role);
        $this->assertSame(OrganizationMembershipStatus::Active, $membership->status);
    }

    public function test_registrant_is_workspace_owner(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Cici',
            'email' => 'cici@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Cici Org',
        ])->assertRedirect();

        $user = User::where('email', 'cici@example.com')->first();
        $org = Organization::where('slug', 'cici-org')->first();

        $this->assertTrue($user->isWorkspaceOwner($org));
    }

    public function test_registration_cannot_create_platform_admin(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Deni',
            'email' => 'deni@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Deni Org',
            'platform_role' => 'superadmin',
            'role' => 'superadmin',
        ]);

        $response->assertStatus(302);

        $user = User::where('email', 'deni@example.com')->first();

        $this->assertNull($user->platform_role);
        $this->assertSame(UserRole::Owner, $user->role);
    }

    public function test_extra_privileged_fields_are_ignored(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Eka',
            'email' => 'eka@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Eka Org',
            'organization_id' => 999,
            'membership_role' => 'admin',
            'is_superadmin' => true,
        ]);

        $response->assertStatus(302);

        $user = User::where('email', 'eka@example.com')->first();
        $org = Organization::where('slug', 'eka-org')->first();

        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertNull($user->platform_role);
        $this->assertSame(OrganizationRole::Owner,
            OrganizationMembership::where('organization_id', $org->id)->first()->role);
    }

    public function test_duplicate_email_fails_validation_and_creates_no_additional_user(): void
    {
        User::factory()->create(['email' => 'first@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Second',
            'email' => 'first@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Second Org',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
        $this->assertSame(0, Organization::count());
        $this->assertSame(0, OrganizationMembership::count());
    }

    public function test_slug_is_unique_per_workspace_name(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Gadun',
            'email' => 'gadun@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Gadun Org',
        ])->assertRedirect();

        // Registration auto-logs-in the new user; log out so the next request
        // is treated as a fresh guest by the `guest` middleware.
        auth()->logout();
        $this->flushSession();

        $this->post(route('register.store'), [
            'name' => 'Gadun',
            'email' => 'gadun2@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Gadun Org',
        ])->assertRedirect();

        $firstOrg = Organization::where('slug', 'gadun-org')->first();
        $secondOrg = Organization::where('slug', 'gadun-org-2')->first();

        $this->assertNotNull($firstOrg);
        $this->assertNotNull($secondOrg);
        $this->assertNotSame($firstOrg->id, $secondOrg->id);
    }

    public function test_failed_validation_writes_nothing(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Hanif',
            'email' => 'hanif@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'workspace_name' => 'Hanif Org',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
        $this->assertSame(0, OrganizationMembership::count());
    }

    public function test_password_rules_are_enforced(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Iwan',
            'email' => 'iwan@example.com',
            'password' => 'Ab1',
            'password_confirmation' => 'Ab1',
            'workspace_name' => 'Iwan Org',
        ])->assertSessionHasErrors('password');

        $this->post(route('register.store'), [
            'name' => 'Jaka',
            'email' => 'jaka@example.com',
            'password' => 'passwordletters',
            'password_confirmation' => 'passwordletters',
            'workspace_name' => 'Jaka Org',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_authenticated_user_can_create_additional_workspace_without_new_user(): void
    {
        $user = User::factory()->owner()->create(['password' => 'password']);
        $existingOrg = Organization::factory()->create();
        OrganizationMembership::factory()->owner()->create([
            'organization_id' => $existingOrg->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(1, User::count());
        $this->assertSame(1, Organization::count());

        $response = $this->actingAs($user)->post(route('workspace.store'), [
            'workspace_name' => 'Kuncoro Org',
        ]);

        $response->assertStatus(302);

        $this->assertSame(1, User::count());
        $this->assertSame(2, Organization::count());

        $newOrg = Organization::where('slug', 'kuncoro-org')->first();
        $this->assertNotNull($newOrg);
        $this->assertSame($user->id, OrganizationMembership::where('organization_id', $newOrg->id)->value('user_id'));
    }

    public function test_registration_never_assigns_legacy_superadmin_or_platform_role(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Lina',
            'email' => 'lina@example.com',
            'password' => 'Abcdefg1',
            'password_confirmation' => 'Abcdefg1',
            'workspace_name' => 'Lina Org',
            'platform_role' => 'superadmin',
            'role' => 'superadmin',
        ])->assertRedirect();

        $user = User::where('email', 'lina@example.com')->first();

        $this->assertNull($user->platform_role);
        $this->assertSame(UserRole::Owner, $user->role);
    }
}
