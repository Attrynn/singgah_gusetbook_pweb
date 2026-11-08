<?php

namespace Tests\Feature\M3;

use App\Enums\RunOfShowStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SetupStatus;
use App\Models\AuditLog;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\EventPhase;
use App\Models\EventRunOfShowItem;
use App\Models\EventSchedule;
use App\Models\EventSession;
use App\Models\EventSetupChecklistItem;
use App\Models\EventSetupConfig;
use App\Models\EventStaffMembership;
use App\Models\EventVenue;
use App\Models\GiftAccount;
use App\Models\GuestbookMessage;
use App\Models\GuestToken;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Rsvp;
use App\Models\User;
use App\Services\EventBlueprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class DuplicateBlueprintTest extends TestCase
{
    use BuildsWedding;
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

    protected function memberOf(Organization $org, string $role): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        return $user;
    }

    /**
     * POST the duplicate route and return the freshly created copy.
     */
    protected function duplicate(Organization $org, Event $source, User $actor, array $body = []): Event
    {
        $before = $org->events()->pluck('id')->all();

        $this->actingAs($actor)
            ->post(route('workspace.events.duplicate', [$org, $source]), $body)
            ->assertRedirect();

        return $org->events()->whereNotIn('id', $before)->firstOrFail();
    }

    /**
     * Build a fully-populated source event so the duplicate can exercise every
     * allowlisted structural copy path at once.
     */
    protected function richSourceEvent(Organization $org): Event
    {
        $event = $this->makeEvent(['organization_id' => $org->id]);

        $phaseA = EventPhase::create([
            'event_id' => $event->id,
            'name' => 'Akad',
            'sequence' => 0,
        ]);
        $phaseB = EventPhase::create([
            'event_id' => $event->id,
            'name' => 'Resepsi',
            'sequence' => 1,
        ]);

        EventSession::create([
            'event_id' => $event->id,
            'phase_id' => $phaseA->id,
            'name' => 'Akad sore',
            'session_date' => now()->addDays(10)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Jakarta',
            'sequence' => 0,
        ]);

        EventSchedule::create([
            'event_id' => $event->id,
            'title' => 'Jadwal Akad',
            'description' => 'Sore hari',
            'start_time' => now()->addDays(10)->setTime(10, 0)->toDateTimeString(),
            'end_time' => now()->addDays(10)->setTime(12, 0)->toDateTimeString(),
            'status' => ScheduleStatus::Planned,
            'sort_order' => 0,
        ]);

        EventRunOfShowItem::create([
            'event_id' => $event->id,
            'phase_id' => $phaseA->id,
            'title' => 'Akad',
            'internal_description' => 'Pembacaan janji',
            'planned_start' => now()->addDays(10)->setTime(10, 0)->toDateTimeString(),
            'planned_end' => now()->addDays(10)->setTime(12, 0)->toDateTimeString(),
            'actual_start' => now()->subDay()->setTime(10, 0)->toDateTimeString(),
            'actual_end' => now()->subDay()->setTime(12, 0)->toDateTimeString(),
            'status' => RunOfShowStatus::Completed,
            'sequence' => 0,
            'pic_staff_membership_id' => null,
            'internal_notes' => 'catatan internal',
        ]);

        EventRunOfShowItem::create([
            'event_id' => $event->id,
            'phase_id' => $phaseB->id,
            'title' => 'Resepsi',
            'internal_description' => 'resepsi',
            'planned_start' => now()->addDays(11)->setTime(14, 0)->toDateTimeString(),
            'planned_end' => now()->addDays(11)->setTime(16, 0)->toDateTimeString(),
            'status' => RunOfShowStatus::Pending,
            'sequence' => 1,
            'pic_staff_membership_id' => null,
            'internal_notes' => 'internal notes here',
        ]);

        EventSetupChecklistItem::create([
            'event_id' => $event->id,
            'label' => 'Undangan',
            'description' => null,
            'required' => true,
            'sequence' => 0,
            'completed' => true,
            'completed_at' => now(),
            'source_key' => 'undangan',
        ]);
        EventSetupChecklistItem::create([
            'event_id' => $event->id,
            'label' => 'Dekorasi',
            'description' => null,
            'required' => false,
            'sequence' => 1,
            'completed' => false,
            'completed_at' => null,
            'source_key' => 'dekorasi',
        ]);

        EventSetupConfig::create([
            'event_id' => $event->id,
            'playbook_id' => null,
            'playbook_version_id' => null,
            'source_event_id' => null,
            'config_snapshot' => [
                'phases' => [
                    ['name' => 'Akad', 'sequence' => 0],
                    ['name' => 'Resepsi', 'sequence' => 1],
                ],
                'checklist' => [
                    ['label' => 'Undangan', 'required' => true, 'sequence' => 0],
                    ['label' => 'Dekorasi', 'required' => false, 'sequence' => 1],
                ],
                'public_schedule' => [
                    ['title' => 'Jadwal Akad', 'description' => 'Sore'],
                ],
                'run_of_show' => [
                    ['title' => 'Akad', 'internal_description' => 'Pembacaan janji'],
                ],
            ],
            'setup_status' => SetupStatus::InProgress,
            'primary_pic_staff_membership_id' => null,
        ]);

        EventVenue::create([
            'event_id' => $event->id,
            'name' => 'Ballroom Utama',
            'address' => 'Jalan Sudirman 1',
            'google_maps_url' => 'https://maps.google.com/?q=x',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        return $event;
    }

    // -------------------------------------------------------------------------
    // §17.8 — Core behaviour
    // -------------------------------------------------------------------------

    public function test_duplicate_creates_new_event_in_same_organization(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $copy = $this->duplicate($org, $source, $actor);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame((int) $org->id, (int) $copy->organization_id);
        $this->assertSame($source->name.' (Salinan)', $copy->name);
    }

    public function test_source_event_is_unchanged_by_duplicate(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $original = $source->fresh();
        $snapshot = [
            'name' => $original->name,
            'groom_name' => $original->groom_name,
            'bride_name' => $original->bride_name,
            'couple_description' => $original->couple_description,
            'description' => $original->description,
            'venue_name' => $original->venue_name,
            'venue_address' => $original->venue_address,
            'google_maps_url' => $original->google_maps_url,
            'start_time' => $original->start_time,
            'end_time' => $original->end_time,
            'timezone' => $original->timezone,
            'status' => $original->status,
            'published_at' => $original->published_at?->toISOString(),
            'archived_at' => $original->archived_at?->toISOString(),
            'updated_at' => $original->updated_at->toISOString(),
        ];

        $this->duplicate($org, $source, $actor);

        $fresh = $source->fresh();

        $this->assertSame($snapshot['name'], $fresh->name);
        $this->assertSame($snapshot['groom_name'], $fresh->groom_name);
        $this->assertSame($snapshot['bride_name'], $fresh->bride_name);
        $this->assertSame($snapshot['couple_description'], $fresh->couple_description);
        $this->assertSame($snapshot['description'], $fresh->description);
        $this->assertSame($snapshot['venue_name'], $fresh->venue_name);
        $this->assertSame($snapshot['venue_address'], $fresh->venue_address);
        $this->assertSame($snapshot['google_maps_url'], $fresh->google_maps_url);
        $this->assertSame($snapshot['start_time'], $fresh->start_time);
        $this->assertSame($snapshot['end_time'], $fresh->end_time);
        $this->assertSame($snapshot['timezone'], $fresh->timezone);
        $this->assertSame($snapshot['status'], $fresh->status);
        $this->assertSame($snapshot['published_at'], $fresh->published_at?->toISOString());
        $this->assertSame($snapshot['archived_at'], $fresh->archived_at?->toISOString());
        $this->assertSame($snapshot['updated_at'], $fresh->updated_at->toISOString());

        $this->assertSame(2, $fresh->phases()->count());
        $this->assertSame(1, $fresh->sessions()->count());
        $this->assertSame(1, $fresh->schedules()->count());
        $this->assertSame(2, $fresh->runOfShowItems()->count());
        $this->assertSame(1, $fresh->venues()->count());
        $this->assertSame(1, $fresh->setupConfig()->count());
    }

    public function test_duplicate_creates_no_extra_rows_on_source(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $tables = [
            'guests', 'guest_tokens', 'rsvps', 'checkins', 'media_assets',
            'guestbook_messages', 'gift_accounts', 'event_user',
            'event_owner_memberships', 'event_staff_memberships',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->where('event_id', $source->id)->count();
        }

        $this->duplicate($org, $source, $actor);

        foreach ($tables as $table) {
            $after = DB::table($table)->where('event_id', $source->id)->count();
            $this->assertSame($before[$table], $after, "Source {$table} grew after duplicate.");
        }
    }

    public function test_duplicate_copies_allowlisted_structure(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $copy = $this->duplicate($org, $source, $actor);

        $this->assertSame(['Akad', 'Resepsi'], $copy->phases()->orderBy('sequence')->pluck('name')->all());
        $this->assertSame([0, 1], $copy->phases()->orderBy('sequence')->pluck('sequence')->all());

        $this->assertSame(['Undangan', 'Dekorasi'], $copy->checklistItems()->orderBy('sequence')->pluck('label')->all());
        $this->assertSame(
            [true, false],
            $copy->checklistItems()->orderBy('sequence')->get()->map(fn ($item) => (bool) $item->required)->all()
        );
        $this->assertSame(
            [false, false],
            $copy->checklistItems()->orderBy('sequence')->get()->map(fn ($item) => (bool) $item->completed)->all(),
            'Copied checklist items must be reset to incomplete.'
        );

        $this->assertSame('Akad sore', $copy->sessions()->first()->name);
        $this->assertSame('Asia/Jakarta', $copy->sessions()->first()->timezone);

        $this->assertSame('Jadwal Akad', $copy->schedules()->first()->title);

        $ro = $copy->runOfShowItems()->orderBy('sequence')->get();
        $this->assertSame('Akad', $ro[0]->title);
        $this->assertSame('Pembacaan janji', $ro[0]->internal_description);
        $this->assertSame('resepsi', $ro[1]->internal_description);
        $this->assertSame(
            now()->addDays(10)->setTime(10, 0)->toDateTimeString(),
            $ro[0]->planned_start?->toDateTimeString()
        );
        $this->assertSame(
            now()->addDays(10)->setTime(12, 0)->toDateTimeString(),
            $ro[0]->planned_end?->toDateTimeString()
        );

        $snapshot = $copy->setupConfig()->first()->config_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertSame('Akad', $snapshot['phases'][0]['name']);
        $this->assertSame('Resepsi', $snapshot['phases'][1]['name']);
        $this->assertSame('Undangan', $snapshot['checklist'][0]['label']);
        $this->assertSame('Jadwal Akad', $snapshot['public_schedule'][0]['title']);
        $this->assertSame('Akad', $snapshot['run_of_show'][0]['title']);
    }

    public function test_duplicate_resets_run_of_show_personal_fields(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $copy = $this->duplicate($org, $source, $actor);

        foreach ($copy->runOfShowItems()->orderBy('sequence')->get() as $item) {
            $this->assertNull($item->pic_staff_membership_id, 'PIC must be reset on duplicate.');
            $this->assertNull($item->internal_notes, 'Internal notes must be reset on duplicate.');
            $this->assertNull($item->actual_start, 'Actual start must be reset on duplicate.');
            $this->assertNull($item->actual_end, 'Actual end must be reset on duplicate.');
            $this->assertSame(RunOfShowStatus::Pending, $item->status);
        }
    }

    public function test_duplicate_excludes_personal_and_transactional_data(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $guest = $this->makeGuest($source);
        $this->makeGuest($source, ['name' => 'Guest Two']);
        $this->invitationToken($guest);
        $this->qrToken($guest);

        Rsvp::create(['event_id' => $source->id, 'guest_id' => $guest->id, 'status' => 'confirmed']);
        Checkin::create([
            'event_id' => $source->id,
            'guest_id' => $guest->id,
            'checked_in_by' => $actor->id,
            'method' => 'qr',
            'checked_in_at' => now(),
        ]);
        MediaAsset::create([
            'event_id' => $source->id,
            'path' => 'media/x.jpg',
            'original_name' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);
        GuestbookMessage::create([
            'event_id' => $source->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Sender',
            'message' => 'hello',
            'visibility' => 'public',
            'status' => 'approved',
        ]);
        GiftAccount::create([
            'event_id' => $source->id,
            'bank_name' => 'Bank ABC',
            'account_number' => '123',
            'account_owner' => 'Owner',
            'is_active' => true,
        ]);
        $source->owners()->attach($actor->id, ['role_label' => 'groom']);
        EventOwnerMembership::create([
            'event_id' => $source->id,
            'user_id' => $actor->id,
            'role_label' => 'groom',
            'status' => 'active',
        ]);
        EventStaffMembership::create([
            'event_id' => $source->id,
            'user_id' => $actor->id,
            'role' => 'event_manager',
            'status' => 'active',
        ]);

        $copy = $this->duplicate($org, $source, $actor);

        $this->assertSame(0, $copy->guests()->count());
        $this->assertSame(0, GuestToken::where('event_id', $copy->id)->count());
        $this->assertSame(0, Rsvp::where('event_id', $copy->id)->count());
        $this->assertSame(0, Checkin::where('event_id', $copy->id)->count());
        $this->assertSame(0, MediaAsset::where('event_id', $copy->id)->count());
        $this->assertSame(0, GuestbookMessage::where('event_id', $copy->id)->count());
        $this->assertSame(0, GiftAccount::where('event_id', $copy->id)->count());
        $this->assertSame(0, DB::table('event_user')->where('event_id', $copy->id)->count());
        $this->assertSame(0, EventOwnerMembership::where('event_id', $copy->id)->count());
        $this->assertSame(0, EventStaffMembership::where('event_id', $copy->id)->count());
    }

    public function test_duplicate_rejects_event_without_organization(): void
    {
        $actor = $this->memberOf($this->organization('dup-org'), 'owner');
        $legacy = $this->makeEvent(['organization_id' => null]);

        try {
            app(EventBlueprintService::class)->duplicate($legacy, [], $actor);
            $this->fail('Duplicating an event without an organization must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_cross_org_actor_cannot_duplicate_under_other_org_path(): void
    {
        $orgA = $this->organization('dup-org-a');
        $orgB = $this->organization('dup-org-b');
        $source = $this->richSourceEvent($orgB);
        $actor = $this->memberOf($orgB, 'owner');

        // Actor belongs to B only; requesting the B event through A's workspace
        // path is denied by the org.member middleware before the controller.
        $this->actingAs($actor)
            ->post(route('workspace.events.duplicate', [$orgA, $source]))
            ->assertForbidden();
    }

    public function test_duplicate_is_atomic_and_creates_expected_child_rows(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $before = $org->events()->count();

        $copy = $this->duplicate($org, $source, $actor);

        $this->assertSame($before + 1, $org->events()->count(), 'Duplicate must add exactly one event.');

        $this->assertSame(2, $copy->phases()->count());
        $this->assertSame(1, $copy->sessions()->count());
        $this->assertSame(1, $copy->schedules()->count());
        $this->assertSame(2, $copy->runOfShowItems()->count());
        $this->assertSame(1, $copy->venues()->count());
        $this->assertSame(1, $copy->setupConfig()->count());
    }

    public function test_duplicate_writes_audit_row_referencing_source(): void
    {
        $org = $this->organization('dup-org');
        $actor = $this->memberOf($org, 'owner');
        $source = $this->richSourceEvent($org);

        $copy = $this->duplicate($org, $source, $actor);

        $log = AuditLog::where('action', 'event.duplicated')->first();

        $this->assertNotNull($log, 'An audit row for event.duplicated was not written.');
        $this->assertSame($copy->id, $log->event_id, 'Audit row must be recorded against the new copy.');
        $this->assertSame($source->id, $log->metadata['source_event_id'], 'Audit metadata must reference the source event.');
    }
}
