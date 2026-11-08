<?php

namespace Tests\Feature\M3;

use App\Enums\EventStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = $this->organization('org-a');
        $this->orgB = $this->organization('org-b');
    }

    private function organization(string $slug): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    private function memberOf(Organization $org, OrganizationRole $role): User
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

    private function createEvents(Organization $org, int $count, array $statuses = []): int
    {
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $status = $statuses[$i] ?? EventStatus::Draft->value;

            Event::factory()->create([
                'organization_id' => $org->id,
                'status' => $status,
                'event_date' => now()->addDays(10 + $i)->toDateString(),
            ]);

            $created++;
        }

        return $created;
    }

    private function indexRoute(Organization $org): string
    {
        return route('workspace.events.index', $org);
    }

    // -------------------------------------------------------------------------
    // Visibility
    // -------------------------------------------------------------------------

    public function test_owner_can_list_own_org_events(): void
    {
        $this->createEvents($this->orgA, 2);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 2;
            });
    }

    public function test_admin_can_list_own_org_events(): void
    {
        $this->createEvents($this->orgA, 3);

        $admin = $this->memberOf($this->orgA, OrganizationRole::Admin);

        $this->actingAs($admin)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 3;
            });
    }

    public function test_user_of_org_a_cannot_see_org_b_events(): void
    {
        $this->createEvents($this->orgA, 2);
        $this->createEvents($this->orgB, 2);

        $ownerA = $this->memberOf($this->orgA, OrganizationRole::Owner);

        // Org B events are invisible to an Org A owner.
        $this->actingAs($ownerA)->get($this->indexRoute($this->orgB))
            ->assertForbidden();

        // Org A events are visible.
        $this->actingAs($ownerA)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 2;
            });
    }

    public function test_unauthenticated_and_non_member_are_forbidden(): void
    {
        $this->createEvents($this->orgA, 1);

        $this->get($this->indexRoute($this->orgA))->assertRedirect(route('login'));

        $stranger = User::factory()->owner()->create(['is_active' => true]);

        $this->actingAs($stranger)->get($this->indexRoute($this->orgA))->assertForbidden();
    }

    public function test_idor_by_manipulating_organization_segment_is_blocked(): void
    {
        $eventB = Event::factory()->create(['organization_id' => $this->orgB->id]);

        $ownerA = $this->memberOf($this->orgA, OrganizationRole::Owner);

        // Swapping the {organization} segment to another org yields 403.
        $this->actingAs($ownerA)->get($this->indexRoute($this->orgB))->assertForbidden();

        // An event owned by Org B is never reachable through an Org A route.
        $this->actingAs($ownerA)->get(route('workspace.events.show', [$this->orgA, $eventB]))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Search / filters / sort — tenant scoped
    // -------------------------------------------------------------------------

    public function test_search_is_tenant_scoped(): void
    {
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Alpha Wedding',
            'venue_name' => 'Venue Alpha',
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Beta Gala',
            'venue_name' => 'Venue Beta',
        ]);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?search=Beta')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 0;
            });

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?search=Alpha')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 1;
            });
    }

    public function test_status_filter_is_tenant_scoped(): void
    {
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Draft->value,
            'event_date' => now()->addDays(10)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Published->value,
            'event_date' => now()->addDays(11)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'status' => EventStatus::Published->value,
            'event_date' => now()->addDays(12)->toDateString(),
        ]);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?status=published')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 1;
            });

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?status=draft')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 1;
            });
    }

    public function test_date_filter_is_tenant_scoped(): void
    {
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Draft->value,
            'event_date' => '2026-01-15',
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'status' => EventStatus::Published->value,
            'event_date' => '2026-06-15',
        ]);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?date_from=2026-05-01')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 0;
            });

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?date_from=2025-12-01')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 1;
            });
    }

    public function test_sort_is_tenant_scoped(): void
    {
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Zebra',
            'event_date' => now()->addDays(10)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Apple',
            'event_date' => now()->addDays(11)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Beta',
            'event_date' => now()->addDays(12)->toDateString(),
        ]);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?sort=name&direction=asc')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                $names = $events->getCollection()->pluck('name')->all();

                return $names === ['Apple', 'Zebra'];
            });
    }

    // -------------------------------------------------------------------------
    // Pagination — tenant scoped
    // -------------------------------------------------------------------------

    public function test_pagination_is_tenant_scoped(): void
    {
        $this->createEvents($this->orgA, 13);
        $this->createEvents($this->orgB, 13);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $page1 = $this->actingAs($owner)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 12;
            });

        $page2 = $this->actingAs($owner)->get($this->indexRoute($this->orgA).'?page=2')
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 1;
            });

        $this->actingAs($owner)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                foreach ($events->getCollection() as $event) {
                    $this->assertSame($this->orgA->id, $event->organization_id);
                }

                return $events->total() === 13;
            });
    }

    // -------------------------------------------------------------------------
    // Grouped status counts
    // -------------------------------------------------------------------------

    public function test_grouped_status_counts_correct(): void
    {
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Draft->value,
            'event_date' => now()->addDays(10)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Draft->value,
            'event_date' => now()->addDays(11)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Published->value,
            'event_date' => now()->addDays(12)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Live->value,
            'event_date' => now()->addDays(13)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Completed->value,
            'event_date' => now()->addDays(14)->toDateString(),
        ]);
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Archived->value,
            'event_date' => now()->addDays(15)->toDateString(),
        ]);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('summary', function ($summary) {
                return $summary === [
                    'total' => 6,
                    'draft' => 2,
                    'upcoming' => 1,
                    'in_progress' => 1,
                    'completed' => 1,
                    'archived' => 1,
                    'setup_incomplete' => 6,
                    'no_manager' => 6,
                ];
            });
    }

    // -------------------------------------------------------------------------
    // Archived events remain visible to authorized users
    // -------------------------------------------------------------------------

    public function test_archived_events_are_returned_to_authorized_users(): void
    {
        Event::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => EventStatus::Archived->value,
            'event_date' => now()->addDays(10)->toDateString(),
        ]);

        $owner = $this->memberOf($this->orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->indexRoute($this->orgA))
            ->assertOk()
            ->assertViewHas('events', function ($events) {
                return $events->count() === 1;
            });
    }
}
