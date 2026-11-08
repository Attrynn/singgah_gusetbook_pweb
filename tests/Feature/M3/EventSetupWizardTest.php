<?php

namespace Tests\Feature\M3;

use App\Enums\EventStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\SetupStatus;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventSetupChecklistItem;
use App\Models\EventSetupConfig;
use App\Models\Guest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlaybookVersion;
use App\Models\User;
use App\Services\EventSetupService;
use Database\Seeders\PlatformPlaybookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function organization(string $slug): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    protected function memberOf(Organization $org, OrganizationRole $role): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => OrganizationMembershipStatus::Active->value,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pernikahan Andi & Sari',
            'groom_name' => 'Andi',
            'bride_name' => 'Sari',
            'event_date' => now()->addDays(30)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '14:00',
            'venue_name' => 'Gedung Serbaguna',
            'venue_address' => 'Jl. Merdeka No. 1',
            'timezone' => 'Asia/Jakarta',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_wizard_creates_event_in_route_organization_as_draft(): void
    {
        $org = $this->organization('wizard-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $response = $this->actingAs($owner)
            ->post(route('workspace.events.store', $org), $this->eventPayload());

        $event = Event::where('organization_id', $org->id)->first();

        $this->assertNotNull($event);
        $this->assertSame(EventStatus::Draft, $event->status);
        $response->assertRedirect(route('workspace.events.setup', [$org, $event]));
    }

    public function test_creating_event_with_playbook_version_applies_phases_and_checklist_without_staff_or_guests(): void
    {
        $this->seed(PlatformPlaybookSeeder::class);

        $org = $this->organization('wizard-playbook-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $version = PlaybookVersion::query()
            ->whereHas('playbook', fn ($query) => $query->whereNull('organization_id'))
            ->firstOrFail();

        $this->actingAs($owner)->post(route('workspace.events.store', $org), $this->eventPayload([
            'playbook_version_id' => $version->id,
        ]));

        $event = Event::where('organization_id', $org->id)->firstOrFail();

        $this->assertGreaterThan(0, $event->phases()->count());
        $this->assertGreaterThan(0, $event->checklistItems()->count());
        $this->assertSame(0, $event->staffMemberships()->count());
        $this->assertSame(0, $event->guests()->count());
        $this->assertSame($version->id, $event->setupConfig->playbook_version_id);
    }

    public function test_organization_id_in_body_is_ignored_and_event_lands_in_route_org(): void
    {
        $orgA = $this->organization('wizard-org-a');
        $orgB = $this->organization('wizard-org-b');
        $ownerA = $this->memberOf($orgA, OrganizationRole::Owner);

        $this->actingAs($ownerA)->post(route('workspace.events.store', $orgA), $this->eventPayload([
            'organization_id' => $orgB->id,
        ]));

        $event = Event::latest('id')->firstOrFail();

        $this->assertSame($orgA->id, $event->organization_id);
        $this->assertSame(0, Event::where('organization_id', $orgB->id)->count());
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_store_rejects_missing_required_fields(): void
    {
        $org = $this->organization('wizard-invalid-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('workspace.events.store', $org), [])
            ->assertSessionHasErrors(['name', 'event_date', 'venue_name', 'venue_address', 'timezone']);

        $this->assertSame(0, Event::where('organization_id', $org->id)->count());
    }

    public function test_store_rejects_end_time_before_start_time(): void
    {
        $org = $this->organization('wizard-time-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('workspace.events.store', $org), $this->eventPayload([
                'start_time' => '14:00',
                'end_time' => '10:00',
            ]))
            ->assertSessionHasErrors('end_time');
    }

    // -------------------------------------------------------------------------
    // Setup save + resume
    // -------------------------------------------------------------------------

    public function test_setup_save_persists_phases_checklist_and_sessions(): void
    {
        $org = $this->organization('wizard-save-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $event = Event::factory()->draft()->create(['organization_id' => $org->id]);

        $sessionDate = now()->addDays(30);

        $this->actingAs($owner)->put(route('workspace.events.setup.save', [$org, $event]), [
            'phases' => [
                ['name' => 'Persiapan', 'sequence' => 0],
                ['name' => 'Resepsi', 'sequence' => 1],
            ],
            'checklist' => [
                ['label' => 'Konfirmasi venue', 'required' => true, 'source_key' => 'venue_confirmed'],
            ],
            'sessions' => [
                [
                    'name' => 'Sesi Pagi',
                    'session_date' => $sessionDate->toDateString(),
                    'start_time' => $sessionDate->copy()->setTime(9, 0)->toDateTimeString(),
                    'end_time' => $sessionDate->copy()->setTime(11, 0)->toDateTimeString(),
                ],
            ],
        ])->assertRedirect();

        $setup = $event->fresh()->setupConfig;

        $this->assertNotNull($setup);
        $this->assertSame('Persiapan', $setup->config_snapshot['phases'][0]['name']);
        $this->assertSame('Konfirmasi venue', $setup->config_snapshot['checklist'][0]['label']);
        $this->assertSame(1, $event->fresh()->sessions()->count());
    }

    public function test_wizard_can_be_resumed_with_reloaded_config(): void
    {
        $org = $this->organization('wizard-resume-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $event = Event::factory()->draft()->create(['organization_id' => $org->id]);

        EventSetupConfig::factory()->forEvent($event)->create([
            'config_snapshot' => ['phases' => [['name' => 'Persiapan']]],
        ]);

        $response = $this->actingAs($owner)->get(route('workspace.events.setup', [$org, $event]));

        $response->assertOk();
        $response->assertViewHas('setup', fn ($setup) => $setup->config_snapshot['phases'][0]['name'] === 'Persiapan');
    }

    // -------------------------------------------------------------------------
    // Readiness + finalize
    // -------------------------------------------------------------------------

    public function test_readiness_is_computed_from_checklist_and_finalize_sets_ready(): void
    {
        $org = $this->organization('wizard-ready-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $event = Event::factory()->draft()->create(['organization_id' => $org->id]);

        EventSetupChecklistItem::factory()->for($event)->required()->create(['source_key' => 'a']);
        EventSetupChecklistItem::factory()->for($event)->required()->create(['source_key' => 'b']);

        $service = app(EventSetupService::class);

        $this->assertSame(SetupStatus::InProgress, $service->evaluate($event)['status']);

        $event->checklistItems()->update(['completed' => true, 'completed_at' => now()]);

        $this->assertSame(SetupStatus::Ready, $service->evaluate($event->fresh())['status']);

        $this->actingAs($owner)
            ->post(route('workspace.events.setup.finalize', [$org, $event]))
            ->assertRedirect(route('workspace.events.show', [$org, $event]));

        $this->assertSame(SetupStatus::Ready, $event->fresh()->setupConfig->setup_status);
    }

    public function test_finalize_keeps_in_progress_when_required_items_incomplete(): void
    {
        $org = $this->organization('wizard-incomplete-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $event = Event::factory()->draft()->create(['organization_id' => $org->id]);

        EventSetupChecklistItem::factory()->for($event)->required()->create(['source_key' => 'a']);
        EventSetupChecklistItem::factory()->for($event)->required()->create(['source_key' => 'b']);

        $this->actingAs($owner)->post(route('workspace.events.setup.finalize', [$org, $event]));

        $this->assertSame(SetupStatus::InProgress, $event->fresh()->setupConfig->setup_status);
    }

    public function test_finalize_never_publishes_event(): void
    {
        $org = $this->organization('wizard-nopublish-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);
        $event = Event::factory()->draft()->create(['organization_id' => $org->id]);

        EventSetupChecklistItem::factory()->for($event)->required()->create(['source_key' => 'a']);

        $event->checklistItems()->update(['completed' => true, 'completed_at' => now()]);

        $this->actingAs($owner)->post(route('workspace.events.setup.finalize', [$org, $event]));

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Duplicate source
    // -------------------------------------------------------------------------

    public function test_duplicate_source_event_creates_structural_copy_in_same_org(): void
    {
        $org = $this->organization('wizard-duplicate-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $source = Event::factory()->draft()->create(['organization_id' => $org->id]);
        EventPhase::factory()->for($source)->create(['name' => 'Persiapan']);
        EventSetupChecklistItem::factory()->for($source)->create(['label' => 'Cek venue']);
        Guest::factory()->create(['event_id' => $source->id]);

        $this->actingAs($owner)->post(route('workspace.events.store', $org), $this->eventPayload([
            'name' => 'Salinan Event',
            'duplicate_source_event_id' => $source->id,
        ]));

        $copy = Event::where('name', 'Salinan Event')->firstOrFail();

        $this->assertSame($org->id, $copy->organization_id);
        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame(1, $copy->phases()->count());
        $this->assertSame(0, $copy->guests()->count());
    }
}
