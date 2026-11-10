<?php

namespace Tests\Feature\M4;

use App\Enums\EventStaffRole;
use App\Enums\GuestPartyStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\RsvpStatus;
use App\Enums\TokenType;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Rsvp;
use App\Models\User;
use App\Services\GuestLegacyMigrationService;
use App\Services\GuestPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestIdentityTest extends TestCase
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

    protected function memberOf(Organization $org, string $role = OrganizationRole::Owner->value): User
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
        ], $attributes));
    }

    public function test_owner_can_create_party_with_quota_and_identified_members(): void
    {
        $org = $this->organization('guest-create');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.guests.store', [$org, $event]), [
                'name' => 'Keluarga Raka',
                'max_attendees' => 3,
                'email' => 'raka@example.test',
                'members' => [
                    ['name' => 'Raka Pratama'],
                    ['name' => 'Alya Putri'],
                ],
            ])
            ->assertRedirect();

        $party = Guest::where('event_id', $event->id)->where('name', 'Keluarga Raka')->firstOrFail();

        $this->assertSame(3, $party->max_attendees);
        $this->assertSame(2, $party->members()->count());
        $this->assertSame(2, GuestPass::where('event_id', $event->id)->count());

        // Credential contract: SHA-256 hash of the raw token, never Hash::make.
        foreach (GuestPass::all() as $pass) {
            $raw = Crypt::decryptString($pass->token_ciphertext);
            $this->assertSame(hash('sha256', $raw), $pass->token_hash);
            $this->assertSame('active', $pass->status);
            $this->assertSame(TokenType::Invitation, $pass->type);
        }
    }

    public function test_member_cannot_be_added_beyond_party_quota(): void
    {
        $org = $this->organization('guest-quota');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create([
            'event_id' => $event->id,
            'name' => 'Keluarga Penuh',
            'max_attendees' => 1,
        ]);

        GuestPartyMembership::create([
            'guest_id' => $party->id,
            'event_id' => $event->id,
            'name' => 'Anggota Pertama',
            'status' => GuestPartyStatus::Active->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.guests.members.store', [$org, $event, $party]), [
                'name' => 'Anggota Kedua',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $party->members()->count());
    }

    public function test_quota_cannot_be_reduced_below_identified_count(): void
    {
        $org = $this->organization('guest-quota-reduce');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create([
            'event_id' => $event->id,
            'name' => 'Keluarga Dua',
            'max_attendees' => 4,
        ]);

        foreach (['Satu', 'Dua'] as $name) {
            GuestPartyMembership::create([
                'guest_id' => $party->id,
                'event_id' => $event->id,
                'name' => $name,
                'status' => GuestPartyStatus::Active->value,
            ]);
        }

        $this->actingAs($owner)
            ->put(route('workspace.events.guests.update', [$org, $event, $party]), [
                'name' => 'Keluarga Dua',
                'max_attendees' => 1,
            ])
            ->assertSessionHasErrors('max_attendees');

        $this->assertSame(4, $party->fresh()->max_attendees);
    }

    public function test_party_from_another_event_is_not_accessible(): void
    {
        $org = $this->organization('guest-tenant');
        $owner = $this->memberOf($org);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);

        $foreignParty = Guest::factory()->create(['event_id' => $eventB->id]);

        $this->actingAs($owner)
            ->get(route('workspace.events.guests.show', [$org, $eventA, $foreignParty]))
            ->assertNotFound();
    }

    public function test_revoking_a_pass_invalidates_resolution(): void
    {
        $org = $this->organization('guest-pass');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create(['event_id' => $event->id, 'max_attendees' => 2]);
        $member = GuestPartyMembership::create([
            'guest_id' => $party->id,
            'event_id' => $event->id,
            'name' => 'Pemegang Pass',
            'status' => GuestPartyStatus::Active->value,
        ]);

        $passes = app(GuestPassService::class);
        $pass = $passes->issue($member, TokenType::Invitation, $owner);
        $raw = Crypt::decryptString($pass->token_ciphertext);

        $this->assertNotNull($passes->resolve($raw, TokenType::Invitation));

        $passes->revoke($pass, $owner, 'Pengujian');

        $this->assertNull($passes->resolve($raw, TokenType::Invitation));
        $this->assertSame('revoked', $pass->fresh()->status);
    }

    public function test_legacy_migration_is_idempotent_and_preserves_rsvp_attendance(): void
    {
        $org = $this->organization('guest-legacy');
        $event = $this->eventIn($org);

        $guest = Guest::factory()->create([
            'event_id' => $event->id,
            'name' => 'Tamu Legacy',
            'max_attendees' => 3,
        ]);

        Rsvp::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'status' => RsvpStatus::Confirmed->value,
            'attendance_count' => 2,
            'responded_at' => now(),
        ]);

        $migration = app(GuestLegacyMigrationService::class);

        $first = $migration->migrate($event, false, null);
        $second = $migration->migrate($event, false, null);

        $this->assertSame(1, $first['total_members_created']);
        $this->assertSame(0, $second['total_members_created']);
        $this->assertSame(1, $second['already_migrated_skipped']);
        $this->assertSame(1, GuestPartyMembership::where('guest_id', $guest->id)->count());

        // Legacy RSVP semantics are untouched.
        $this->assertSame(2, Rsvp::where('guest_id', $guest->id)->value('attendance_count'));

        // Unidentified quota slots are retained as a number, not fabricated rows.
        $this->assertSame(2, $first['unidentified_capacity_retained']);
        $this->assertSame(0, GuestPartyMembership::where('guest_id', $guest->id)->where('name', '!=', 'Tamu Legacy')->count());
    }

    public function test_guest_workspace_pages_render_for_authorized_owner(): void
    {
        $org = $this->organization('guest-pages');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create([
            'event_id' => $event->id,
            'name' => 'Keluarga Tampil',
            'max_attendees' => 2,
        ]);

        GuestPartyMembership::create([
            'guest_id' => $party->id,
            'event_id' => $event->id,
            'name' => 'Anggota Tampil',
            'status' => GuestPartyStatus::Active->value,
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.events.guests.index', [$org, $event]))
            ->assertOk()
            ->assertSee('Keluarga Tampil');

        $this->actingAs($owner)->get(route('workspace.events.guests.create', [$org, $event]))->assertOk();
        $this->actingAs($owner)->get(route('workspace.events.guests.individuals', [$org, $event]))->assertOk()->assertSee('Anggota Tampil');
        $this->actingAs($owner)->get(route('workspace.events.guests.import', [$org, $event]))->assertOk();
        $this->actingAs($owner)->get(route('workspace.events.guests.show', [$org, $event, $party]))->assertOk()->assertSee('Anggota Tampil');
        $this->actingAs($owner)->get(route('workspace.events.guests.edit', [$org, $event, $party]))->assertOk();
    }

    public function test_checkin_staff_cannot_create_parties(): void
    {
        $org = $this->organization('guest-deny');
        $staff = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        EventStaffMembership::create([
            'event_id' => $event->id,
            'user_id' => $staff->id,
            'role' => EventStaffRole::CheckinStaff->value,
            'status' => MembershipStatus::Active->value,
            'assigned_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->post(route('workspace.events.guests.store', [$org, $event]), [
                'name' => 'Tidak Boleh',
                'max_attendees' => 2,
            ])
            ->assertForbidden();

        $this->assertSame(0, Guest::where('event_id', $event->id)->count());
    }
}
