<?php

namespace Tests\Feature;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventAnnouncementRecipient;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\EventTaskComment;
use App\Models\Organization;
use App\Models\User;
use App\Services\OperationsReportService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class OperationsQueryPerformanceTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    /**
     * Delta ceiling under which the query count is considered row-count
     * independent. Anything below this would not fit a per-row query pattern.
     */
    private const DELTA_CEILING = 3;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    private function queryCountForRequest(Organization $organization, Event $event, User $actor, string $routeName): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)
            ->get(route($routeName, [$organization, $event]))
            ->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  Closure():mixed  $callback
     */
    private function queryCountFor(Closure $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function seedAnnouncements(Event $event, User $owner, User $staffA, User $staffB, int $from, int $to): void
    {
        for ($i = $from; $i < $to; $i++) {
            $announcement = EventAnnouncement::create([
                'event_id' => $event->id,
                'title' => 'A'.$i,
                'body' => 'Body '.$i,
                'status' => AnnouncementStatus::Published->value,
                'audience' => AnnouncementAudience::AllStaff->value,
                'priority' => AnnouncementPriority::Normal->value,
                'requires_acknowledgement' => true,
                'published_at' => now(),
                'created_by' => $owner->id,
                'published_by' => $owner->id,
            ]);

            foreach ([$staffA, $staffB] as $position => $recipient) {
                EventAnnouncementRecipient::create([
                    'event_announcement_id' => $announcement->id,
                    'user_id' => $recipient->id,
                    'delivered_at' => now(),
                    'read_at' => $position === 0 ? now() : null,
                    'acknowledged_at' => $position === 0 ? now() : null,
                ]);
            }
        }
    }

    private function seedTasks(Event $event, int $from, int $to): void
    {
        for ($i = $from; $i < $to; $i++) {
            EventTask::create([
                'event_id' => $event->id,
                'title' => 'T'.$i,
            ]);
        }
    }

    private function seedStations(Event $event, int $from, int $to): void
    {
        for ($i = $from; $i < $to; $i++) {
            EventStation::create([
                'event_id' => $event->id,
                'name' => 'S'.$i,
                'slug' => 's-'.$i,
            ]);
        }
    }

    private function seedStaffWithTasks(Organization $organization, Event $event, int $from, int $to): void
    {
        for ($i = $from; $i < $to; $i++) {
            $membership = $this->staffOf($organization, $event, EventStaffRole::GeneralStaff);

            EventTask::create([
                'event_id' => $event->id,
                'title' => 'Member task '.$i,
                'assigned_membership_id' => $membership->id,
            ]);
        }
    }

    public function test_announcements_index_query_count_does_not_scale_with_rows(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staffA = User::factory()->create();
        $staffB = User::factory()->create();

        $this->seedAnnouncements($event, $owner, $staffA, $staffB, 0, 3);
        $small = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.announcements.index');

        $this->seedAnnouncements($event, $owner, $staffA, $staffB, 3, 18);
        $large = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.announcements.index');

        $this->assertLessThanOrEqual(
            self::DELTA_CEILING,
            $large - $small,
            'Announcements index grew by '.($large - $small)." queries going from 3 to 18 rows (small={$small}, large={$large}).",
        );
    }

    public function test_tasks_index_query_count_does_not_scale_with_rows(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->seedTasks($event, 0, 3);
        $small = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.tasks.index');

        $this->seedTasks($event, 3, 18);
        $large = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.tasks.index');

        $this->assertLessThanOrEqual(
            self::DELTA_CEILING,
            $large - $small,
            'Tasks index grew by '.($large - $small)." queries going from 3 to 18 rows (small={$small}, large={$large}).",
        );
    }

    public function test_stations_index_query_count_does_not_scale_with_rows(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->seedStations($event, 0, 3);
        $small = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.stations.index');

        $this->seedStations($event, 3, 18);
        $large = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.stations.index');

        $this->assertLessThanOrEqual(
            self::DELTA_CEILING,
            $large - $small,
            'Stations index grew by '.($large - $small)." queries going from 3 to 18 rows (small={$small}, large={$large}).",
        );
    }

    public function test_reports_index_query_count_does_not_scale_with_staff_rows(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->seedStaffWithTasks($org, $event, 0, 3);
        $small = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.reports.index');

        $this->seedStaffWithTasks($org, $event, 3, 18);
        $large = $this->queryCountForRequest($org, $event, $owner, 'workspace.events.operations.reports.index');

        $this->assertLessThanOrEqual(
            self::DELTA_CEILING,
            $large - $small,
            'Reports index grew by '.($large - $small)." queries going from 3 to 18 staff rows (small={$small}, large={$large}).",
        );
    }

    public function test_staff_report_open_task_counts_do_not_issue_one_query_per_membership(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);

        $this->seedStaffWithTasks($org, $event, 0, 3);
        $small = $this->queryCountFor(
            fn () => app(OperationsReportService::class)->staffReport($event),
        );

        $this->seedStaffWithTasks($org, $event, 3, 12);
        $large = $this->queryCountFor(
            fn () => app(OperationsReportService::class)->staffReport($event),
        );

        $this->assertLessThanOrEqual(
            self::DELTA_CEILING,
            $large - $small,
            'staffReport grew by '.($large - $small)." queries going from 3 to 12 memberships (small={$small}, large={$large}).",
        );
    }

    /**
     * The tasks index eager-loads comments with a per-parent limit. Guard
     * against the eager-load limit degrading into a single global limit (which
     * would drop later tasks' comments entirely) or losing the latest-first
     * ordering.
     */
    public function test_tasks_index_shows_only_the_latest_comments_per_task(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $author = User::factory()->create();

        $taskWithComments = EventTask::create(['event_id' => $event->id, 'title' => 'Tugas berkomentar']);
        EventTask::create(['event_id' => $event->id, 'title' => 'Tugas sunyi']);

        $base = now()->subHours(2);

        for ($i = 1; $i <= 8; $i++) {
            $comment = EventTaskComment::create([
                'event_task_id' => $taskWithComments->id,
                'user_id' => $author->id,
                'body' => 'Komentar '.$i,
            ]);

            // created_at is not fillable, so set it explicitly to make the
            // "latest first" ordering deterministic.
            DB::table('event_task_comments')
                ->where('id', $comment->id)
                ->update(['created_at' => $base->copy()->addMinutes($i)]);
        }

        $this->actingAs($owner)
            ->get(route('workspace.events.operations.tasks.index', [$org, $event]))
            ->assertOk()
            ->assertSee('Komentar 8')
            ->assertSee('Komentar 7')
            ->assertSee('Komentar 6')
            ->assertSee('Komentar 5')
            ->assertSee('Komentar 4')
            ->assertDontSee('Komentar 3')
            ->assertDontSee('Komentar 2')
            ->assertDontSee('Komentar 1');
    }
}
