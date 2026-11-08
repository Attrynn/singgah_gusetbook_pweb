<?php

namespace Tests\Feature\M3;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\PlaybookStatus;
use App\Models\Event;
use App\Models\EventSetupConfig;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Playbook;
use App\Models\User;
use App\Services\PlaybookService;
use Database\Seeders\PlatformPlaybookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaybookTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Platform templates
    // -------------------------------------------------------------------------

    public function test_platform_playbooks_available_after_seeding(): void
    {
        $this->seed(PlatformPlaybookSeeder::class);

        $this->assertSame(5, Playbook::whereNull('organization_id')->count());
        $this->assertSame(
            5,
            Playbook::whereNull('organization_id')->where('status', PlaybookStatus::Active->value)->count(),
        );
        $this->assertSame(5, app(PlaybookService::class)->platformTemplates()->count());
    }

    // -------------------------------------------------------------------------
    // Organization playbooks
    // -------------------------------------------------------------------------

    public function test_organization_playbook_can_be_created(): void
    {
        $org = $this->organization('playbook-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $response = $this->actingAs($owner)->post(route('workspace.playbooks.store', $org), [
            'name' => 'Playbook Organisasi',
            'description' => 'Deskripsi playbook',
            'days' => 2,
            'config' => [
                'phases' => [['name' => 'Persiapan']],
                'checklist' => [['label' => 'Cek venue', 'required' => true]],
            ],
        ]);

        $playbook = Playbook::where('name', 'Playbook Organisasi')->firstOrFail();

        $this->assertSame($org->id, $playbook->organization_id);
        $this->assertSame(1, $playbook->versions()->count());
        $response->assertRedirect(route('workspace.playbooks.show', [$org, $playbook]));
    }

    public function test_org_cannot_read_another_org_playbook(): void
    {
        $orgA = $this->organization('playbook-org-a');
        $orgB = $this->organization('playbook-org-b');
        $ownerA = $this->memberOf($orgA, OrganizationRole::Owner);

        $playbookB = Playbook::factory()->forOrganization($orgB)->create();

        $this->actingAs($ownerA)
            ->get(route('workspace.playbooks.show', [$orgA, $playbookB]))
            ->assertNotFound();
    }

    public function test_org_cannot_modify_another_org_playbook(): void
    {
        $orgA = $this->organization('playbook-mod-org-a');
        $orgB = $this->organization('playbook-mod-org-b');
        $ownerA = $this->memberOf($orgA, OrganizationRole::Owner);

        $playbookB = Playbook::factory()->forOrganization($orgB)->create();

        $this->actingAs($ownerA)
            ->put(route('workspace.playbooks.update', [$orgA, $playbookB]), ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertNotSame('Hacked', $playbookB->fresh()->name);
    }

    public function test_platform_playbook_cannot_be_edited_by_an_org(): void
    {
        $org = $this->organization('playbook-platform-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $platform = Playbook::factory()->platform()->create();

        $this->actingAs($owner)
            ->put(route('workspace.playbooks.update', [$org, $platform]), ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertNotSame('Hacked', $platform->fresh()->name);
    }

    public function test_playbook_can_be_duplicated_into_an_org(): void
    {
        $this->seed(PlatformPlaybookSeeder::class);

        $org = $this->organization('playbook-dup-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $platform = Playbook::whereNull('organization_id')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('workspace.playbooks.duplicate', [$org, $platform]))
            ->assertRedirect();

        $copy = Playbook::where('organization_id', $org->id)->firstOrFail();

        $this->assertStringContainsString('Salinan', $copy->name);
        $this->assertSame(1, $copy->versions()->count());
    }

    // -------------------------------------------------------------------------
    // Versioning + snapshot immutability
    // -------------------------------------------------------------------------

    public function test_editing_config_creates_new_version_and_keeps_old_versions(): void
    {
        $org = $this->organization('playbook-version-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $playbook = Playbook::factory()->forOrganization($org)->create();
        $playbook->versions()->create([
            'version' => 1,
            'config' => ['phases' => [['name' => 'Versi Satu']]],
        ]);

        $this->actingAs($owner)->put(route('workspace.playbooks.update', [$org, $playbook]), [
            'name' => $playbook->name,
            'config' => ['phases' => [['name' => 'Versi Dua']]],
        ]);

        $playbook->refresh();

        $this->assertSame(2, $playbook->versions()->count());
        $this->assertSame(2, $playbook->latestVersion->version);
        $this->assertSame(
            'Versi Satu',
            $playbook->versions()->where('version', 1)->firstOrFail()->config['phases'][0]['name'],
        );
    }

    public function test_event_snapshot_is_immutable_when_playbook_gets_new_version(): void
    {
        $org = $this->organization('playbook-snapshot-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $playbook = Playbook::factory()->forOrganization($org)->create();
        $versionOne = $playbook->versions()->create([
            'version' => 1,
            'config' => ['phases' => [['name' => 'Versi Satu']]],
        ]);

        $event = Event::factory()->draft()->create(['organization_id' => $org->id]);
        $setup = EventSetupConfig::factory()->forEvent($event)->create([
            'playbook_id' => $playbook->id,
            'playbook_version_id' => $versionOne->id,
            'config_snapshot' => $versionOne->config,
        ]);

        $this->actingAs($owner)->put(route('workspace.playbooks.update', [$org, $playbook]), [
            'name' => $playbook->name,
            'config' => ['phases' => [['name' => 'Versi Dua']]],
        ]);

        $setup->refresh();

        $this->assertSame($versionOne->id, $setup->playbook_version_id);
        $this->assertSame('Versi Satu', $setup->config_snapshot['phases'][0]['name']);
        $this->assertSame(2, $playbook->fresh()->latestVersion->version);
    }

    // -------------------------------------------------------------------------
    // Deactivate + authorization
    // -------------------------------------------------------------------------

    public function test_deactivate_sets_status_inactive(): void
    {
        $org = $this->organization('playbook-deactivate-org');
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $playbook = Playbook::factory()->forOrganization($org)->create();

        $this->actingAs($owner)
            ->post(route('workspace.playbooks.deactivate', [$org, $playbook]))
            ->assertRedirect();

        $this->assertSame(PlaybookStatus::Inactive, $playbook->fresh()->status);
    }

    public function test_plain_member_cannot_manage_playbooks(): void
    {
        $org = $this->organization('playbook-member-org');
        $member = $this->memberOf($org, OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('workspace.playbooks.index', $org))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('workspace.playbooks.store', $org), ['name' => 'Tidak Boleh'])
            ->assertForbidden();

        $this->assertSame(0, Playbook::where('organization_id', $org->id)->count());
    }
}
