<?php

namespace Tests\Feature\M6;

use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventAnnouncementRecipient;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the operations announcements screen rendering an
 * event that already has announcements. The Blade helpers type-hint the
 * announcement models, so this fails hard (500) if those classes are not
 * imported; it must render whenever at least one announcement exists.
 */
class AnnouncementPageRenderTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_announcements_index_renders_with_existing_announcements(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = EventAnnouncement::create([
            'event_id' => $event->id,
            'title' => 'Briefing hari H',
            'body' => 'Kumpul di lobi pukul 06.00.',
            'status' => 'published',
            'audience' => 'all_staff',
            'priority' => 'high',
            'requires_acknowledgement' => true,
            'created_by' => $owner->id,
            'published_at' => now(),
        ]);

        EventAnnouncementRecipient::create([
            'event_announcement_id' => $announcement->id,
            'user_id' => $owner->id,
            'delivered_at' => now(),
            'read_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.events.operations.announcements.index', [$org, $event]))
            ->assertOk()
            ->assertSee('Briefing hari H')
            ->assertSee('Terbaca');
    }
}
