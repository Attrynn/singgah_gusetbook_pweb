<?php

namespace Tests\Feature\M5;

use App\Enums\EventStaffRole;
use App\Enums\GuestPartyStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\RsvpStatus;
use App\Enums\TokenType;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Rsvp;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\GuestPassService;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestLifecycleTest extends TestCase
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
            'status' => 'published',
        ], $attributes));
    }

    /**
     * Create a party with one identified member carrying a QR pass.
     *
     * @return array{party: Guest, member: GuestPartyMembership, raw: string}
     */
    protected function partyWithQrPass(Event $event, string $name = 'Budi', int $quota = 2): array
    {
        $party = Guest::factory()->create([
            'event_id' => $event->id,
            'name' => 'Keluarga '.$name,
            'max_attendees' => $quota,
        ]);

        $member = GuestPartyMembership::create([
            'guest_id' => $party->id,
            'event_id' => $event->id,
            'name' => $name,
            'status' => GuestPartyStatus::Active->value,
        ]);

        $pass = app(GuestPassService::class)->issue($member, TokenType::Qr);

        return [
            'party' => $party,
            'member' => $member,
            'pass' => $pass,
            'raw' => Crypt::decryptString($pass->token_ciphertext),
        ];
    }

    public function test_individual_pass_scan_checks_in_party_and_attributes_membership(): void
    {
        $org = $this->organization('m5-scan');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $data = $this->partyWithQrPass($event, 'Budi');

        $this->actingAs($owner)
            ->postJson(route('workspace.events.checkins.scan', [$org, $event]), [
                'token' => $data['raw'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $checkin = Checkin::where('event_id', $event->id)->firstOrFail();
        $this->assertSame($data['party']->id, $checkin->guest_id);
        $this->assertSame($data['member']->id, $checkin->guest_party_membership_id);
        $this->assertSame($data['pass']->id, $checkin->guest_pass_id);
        $this->assertNotNull($data['party']->fresh()->checked_in_at);
        $this->assertNotNull($data['pass']->fresh()->last_used_at);
    }

    public function test_duplicate_individual_pass_scan_is_rejected(): void
    {
        $org = $this->organization('m5-dup');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);
        $data = $this->partyWithQrPass($event);

        $this->actingAs($owner)->postJson(route('workspace.events.checkins.scan', [$org, $event]), ['token' => $data['raw']])->assertOk()->assertJsonPath('status', 'success');
        $this->actingAs($owner)->postJson(route('workspace.events.checkins.scan', [$org, $event]), ['token' => $data['raw']])->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, Checkin::where('event_id', $event->id)->count());
    }

    public function test_revoked_individual_pass_scan_is_rejected(): void
    {
        $org = $this->organization('m5-revoked');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);
        $data = $this->partyWithQrPass($event);

        app(GuestPassService::class)->revoke($data['pass'], $owner, 'Test');

        $this->actingAs($owner)
            ->postJson(route('workspace.events.checkins.scan', [$org, $event]), ['token' => $data['raw']])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $this->assertSame(0, Checkin::where('event_id', $event->id)->count());
    }

    public function test_expired_individual_pass_scan_is_rejected(): void
    {
        $org = $this->organization('m5-expired');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);
        $data = $this->partyWithQrPass($event);

        $data['pass']->forceFill(['expires_at' => now()->subDay()])->save();

        $this->actingAs($owner)
            ->postJson(route('workspace.events.checkins.scan', [$org, $event]), ['token' => $data['raw']])
            ->assertOk()
            ->assertJsonPath('status', 'expired');

        $this->assertSame(0, Checkin::where('event_id', $event->id)->count());
    }

    public function test_pass_from_another_event_is_rejected_as_wrong_event(): void
    {
        $org = $this->organization('m5-cross');
        $owner = $this->memberOf($org);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);

        $data = $this->partyWithQrPass($eventB, 'Lain');

        $this->actingAs($owner)
            ->postJson(route('workspace.events.checkins.scan', [$org, $eventA]), ['token' => $data['raw']])
            ->assertOk()
            ->assertJsonPath('status', 'wrong_event');

        $this->assertSame(0, Checkin::where('event_id', $eventA->id)->count());
    }

    public function test_legacy_party_qr_still_checks_in(): void
    {
        $org = $this->organization('m5-legacy');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create(['event_id' => $event->id, 'name' => 'Tamu Legacy']);
        $raw = app(InvitationService::class)->ensureQrToken($party);

        $this->actingAs($owner)
            ->postJson(route('workspace.events.checkins.scan', [$org, $event]), ['token' => $raw])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $checkin = Checkin::where('event_id', $event->id)->firstOrFail();
        $this->assertNull($checkin->guest_party_membership_id);
        $this->assertSame($party->id, $checkin->guest_id);
    }

    public function test_manual_check_in_records_officer(): void
    {
        $org = $this->organization('m5-manual');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);
        $party = Guest::factory()->create(['event_id' => $event->id, 'name' => 'Manual Tamu']);

        $this->actingAs($owner)
            ->post(route('workspace.events.checkins.manual', [$org, $event]), [
                'guest_id' => $party->id,
                'notes' => 'Registrasi manual',
            ])
            ->assertRedirect();

        $checkin = Checkin::where('event_id', $event->id)->firstOrFail();
        $this->assertSame($owner->id, $checkin->checked_in_by);
        $this->assertSame('manual', $checkin->method->value);
    }

    public function test_checkin_staff_can_scan_but_cannot_manage_guests(): void
    {
        $org = $this->organization('m5-staff');
        $staff = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        EventStaffMembership::create([
            'event_id' => $event->id,
            'user_id' => $staff->id,
            'role' => EventStaffRole::CheckinStaff->value,
            'status' => MembershipStatus::Active->value,
            'assigned_by' => $staff->id,
        ]);

        $data = $this->partyWithQrPass($event);

        $this->actingAs($staff)
            ->postJson(route('workspace.events.checkins.scan', [$org, $event]), ['token' => $data['raw']])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($staff)
            ->post(route('workspace.events.guests.store', [$org, $event]), ['name' => 'Tidak Boleh'])
            ->assertForbidden();
    }

    public function test_guest_manager_without_scan_permission_cannot_open_scanner(): void
    {
        $org = $this->organization('m5-denyscan');
        $staff = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        EventStaffMembership::create([
            'event_id' => $event->id,
            'user_id' => $staff->id,
            'role' => EventStaffRole::GuestManager->value,
            'status' => MembershipStatus::Active->value,
            'assigned_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('workspace.events.checkins.scanner', [$org, $event]))
            ->assertForbidden();
    }

    public function test_checkin_history_is_event_scoped(): void
    {
        $org = $this->organization('m5-history');
        $owner = $this->memberOf($org);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);

        $partyB = Guest::factory()->create(['event_id' => $eventB->id, 'name' => 'Rahasia B']);

        // Staff view of event A must not see event B records.
        $this->actingAs($owner)
            ->get(route('workspace.events.checkins.index', [$org, $eventA]))
            ->assertOk()
            ->assertDontSee('Rahasia B');
    }

    public function test_attendance_dashboard_metrics_are_consistent(): void
    {
        $org = $this->organization('m5-dashboard');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create([
            'event_id' => $event->id,
            'name' => 'Keluarga Metrik',
            'max_attendees' => 4,
        ]);

        $member = GuestPartyMembership::create([
            'guest_id' => $party->id,
            'event_id' => $event->id,
            'name' => 'Metrik Satu',
            'status' => GuestPartyStatus::Active->value,
        ]);

        app(GuestPassService::class)->issue($member, TokenType::Qr);

        Rsvp::create([
            'event_id' => $event->id,
            'guest_id' => $party->id,
            'status' => RsvpStatus::Confirmed->value,
            'attendance_count' => 2,
            'responded_at' => now(),
        ]);

        $stats = app(AttendanceService::class)->dashboardStats($event);

        $this->assertSame(1, $stats['invitations']['parties']);
        $this->assertSame(4, $stats['invitations']['quota']);
        $this->assertSame(1, $stats['guests']['identified']);
        $this->assertSame(3, $stats['guests']['unidentified_capacity']);
        $this->assertSame(1, $stats['rsvp']['confirmed']);
        $this->assertSame(2, $stats['rsvp']['attendance_count']);
        $this->assertSame(1, $stats['passes']['issued']);
        $this->assertSame(1, $stats['passes']['active']);
        $this->assertSame(0, $stats['checkins']['total']);
        $this->assertSame(1, $stats['checkins']['not_checked_in']);
    }

    public function test_attendance_dashboard_scoped_to_event_and_org(): void
    {
        $org = $this->organization('m5-scope-dash');
        $owner = $this->memberOf($org);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);

        Guest::factory()->create(['event_id' => $eventB->id, 'name' => 'B', 'max_attendees' => 9]);

        $stats = app(AttendanceService::class)->dashboardStats($eventA);

        $this->assertSame(0, $stats['invitations']['parties']);
        $this->assertSame(0, $stats['invitations']['quota']);
    }

    public function test_checkin_pages_render_for_authorized_owner(): void
    {
        $org = $this->organization('m5-pages');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $this->actingAs($owner)
            ->get(route('workspace.events.checkins.index', [$org, $event]))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('workspace.events.checkins.scanner', [$org, $event]))
            ->assertOk();
    }

    public function test_attendance_dashboard_renders_hourly_distribution(): void
    {
        $org = $this->organization('m5-dist');
        $owner = $this->memberOf($org);
        $event = $this->eventIn($org);

        $party = Guest::factory()->create(['event_id' => $event->id, 'name' => 'Tamu Distribusi']);

        Checkin::create([
            'event_id' => $event->id,
            'guest_id' => $party->id,
            'checked_in_by' => $owner->id,
            'method' => 'manual',
            'checked_in_at' => '2026-11-10 14:30:00',
        ]);

        $distribution = app(AttendanceService::class)->checkinDistribution($event);

        $this->assertSame('2026-11-10 14:00', $distribution[0]['hour']);
        $this->assertSame(1, $distribution[0]['count']);

        $this->actingAs($owner)
            ->get(route('workspace.events.checkins.index', [$org, $event]))
            ->assertOk()
            ->assertSee('Distribusi Check-in per Jam')
            ->assertSee('2026-11-10 14:00');
    }
}
