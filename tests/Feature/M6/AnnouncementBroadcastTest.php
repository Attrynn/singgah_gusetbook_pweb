<?php

namespace Tests\Feature\M6;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EventAnnouncementPublished;
use App\Services\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class AnnouncementBroadcastTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private AnnouncementService $announcements;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->announcements = app(AnnouncementService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_creating_an_announcement_requires_management_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->expectException(HttpException::class);

        $this->announcements->create($event, ['title' => 'Info', 'body' => 'Isi'], $general->user);
    }

    public function test_station_audience_requires_at_least_one_station(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->expectException(ValidationException::class);

        $this->announcements->create($event, [
            'title' => 'Info',
            'body' => 'Isi',
            'audience' => AnnouncementAudience::Station->value,
            'audience_ref' => [],
        ], $owner);
    }

    public function test_selected_staff_audience_rejects_foreign_memberships(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->staffOf($org, $other, EventStaffRole::GeneralStaff);

        $this->expectException(ValidationException::class);

        $this->announcements->create($event, [
            'title' => 'Info',
            'body' => 'Isi',
            'audience' => AnnouncementAudience::SelectedStaff->value,
            'audience_ref' => ['membership_ids' => [$foreign->id]],
        ], $owner);
    }

    public function test_publishing_to_all_staff_reaches_every_active_member(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $a = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);
        $b = $this->staffOf($org, $event, EventStaffRole::CheckinStaff);

        $announcement = $this->announcements->create($event, [
            'title' => 'Briefing',
            'body' => 'Kumpul di lobi',
        ], $owner);

        $published = $this->announcements->publish($event, $announcement, $owner);

        $this->assertSame(AnnouncementStatus::Published, $published->status);
        $this->assertSame(2, $published->recipients()->count());
        $this->assertTrue($published->recipients()->where('user_id', $a->user_id)->exists());
        $this->assertTrue($published->recipients()->where('user_id', $b->user_id)->exists());

        Notification::assertSentTo($a->user, EventAnnouncementPublished::class);
    }

    public function test_station_audience_only_reaches_assigned_staff(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $assigned = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);
        $outsider = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $station = EventStation::create([
            'event_id' => $event->id,
            'name' => 'Registrasi',
            'slug' => 'registrasi',
            'category' => 'registration',
            'status' => 'ready',
        ]);

        EventStationAssignment::create([
            'event_id' => $event->id,
            'station_id' => $station->id,
            'staff_membership_id' => $assigned->id,
            'assigned_at' => now(),
        ]);

        $announcement = $this->announcements->create($event, [
            'title' => 'Info stasiun',
            'body' => 'Cek meja',
            'audience' => AnnouncementAudience::Station->value,
            'audience_ref' => ['station_ids' => [$station->id]],
        ], $owner);

        $this->announcements->publish($event, $announcement, $owner);

        $this->assertTrue($announcement->recipients()->where('user_id', $assigned->user_id)->exists());
        $this->assertFalse($announcement->recipients()->where('user_id', $outsider->user_id)->exists());
    }

    public function test_profile_audience_matches_staff_roles(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $supervisor = $this->staffOf($org, $event, EventStaffRole::StationSupervisor);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $announcement = $this->announcements->create($event, [
            'title' => 'Info supervisor',
            'body' => 'Rapat supervisor',
            'audience' => AnnouncementAudience::Profile->value,
            'audience_ref' => ['roles' => [EventStaffRole::StationSupervisor->value]],
        ], $owner);

        $this->announcements->publish($event, $announcement, $owner);

        $this->assertTrue($announcement->recipients()->where('user_id', $supervisor->user_id)->exists());
        $this->assertFalse($announcement->recipients()->where('user_id', $general->user_id)->exists());
    }

    public function test_read_and_acknowledgement_are_distinct_persisted_facts(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $announcement = $this->announcements->create($event, [
            'title' => 'Penting',
            'body' => 'Wajib konfirmasi',
            'requires_acknowledgement' => true,
        ], $owner);
        $this->announcements->publish($event, $announcement, $owner);

        $read = $this->announcements->markRead($announcement, $staff->user);
        $this->assertNotNull($read->read_at);
        $this->assertNull($read->acknowledged_at);

        $acknowledged = $this->announcements->acknowledge($announcement, $staff->user);
        $this->assertNotNull($acknowledged->acknowledged_at);
    }

    public function test_acknowledgement_is_rejected_when_not_required(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $announcement = $this->announcements->create($event, [
            'title' => 'Info',
            'body' => 'Tidak wajib konfirmasi',
        ], $owner);
        $this->announcements->publish($event, $announcement, $owner);

        $this->expectException(ValidationException::class);

        $this->announcements->acknowledge($announcement, $staff->user);
    }

    public function test_non_recipient_cannot_acknowledge(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);
        $outsider = $this->memberOf($org);

        $announcement = $this->announcements->create($event, [
            'title' => 'Info',
            'body' => 'Isi',
            'requires_acknowledgement' => true,
        ], $owner);
        $this->announcements->publish($event, $announcement, $owner);

        $this->assertTrue($announcement->recipients()->where('user_id', $staff->user_id)->exists());

        $this->expectException(HttpException::class);

        $this->announcements->acknowledge($announcement, $outsider);
    }

    public function test_published_announcement_is_immutable(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = $this->announcements->create($event, ['title' => 'Info', 'body' => 'Isi'], $owner);
        $this->announcements->publish($event, $announcement, $owner);

        $this->expectException(ValidationException::class);

        $this->announcements->update($event, $announcement->fresh(), ['title' => 'Diubah'], $owner);
    }

    public function test_published_announcement_can_be_withdrawn(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = $this->announcements->create($event, ['title' => 'Info', 'body' => 'Isi'], $owner);
        $this->announcements->publish($event, $announcement, $owner);

        $withdrawn = $this->announcements->withdraw($event, $announcement->fresh(), $owner);

        $this->assertSame(AnnouncementStatus::Withdrawn, $withdrawn->status);
        $this->assertNotNull($withdrawn->withdrawn_at);
    }

    public function test_due_announcements_expire(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = $this->announcements->create($event, [
            'title' => 'Info',
            'body' => 'Isi',
            'expires_at' => now()->subHour()->toDateTimeString(),
        ], $owner);
        $this->announcements->publish($event, $announcement, $owner);

        $count = $this->announcements->expireDue($event);

        $this->assertSame(1, $count);
        $this->assertSame(AnnouncementStatus::Expired, $announcement->fresh()->status);
    }

    public function test_announcement_from_another_event_is_not_found(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->announcements->create($other, ['title' => 'Lain', 'body' => 'Isi'], $owner);

        $this->expectException(HttpException::class);

        $this->announcements->publish($event, $foreign, $owner);
    }

    public function test_critical_announcements_require_acknowledgement_by_default(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = $this->announcements->create($event, [
            'title' => 'Darurat',
            'body' => 'Evakuasi',
            'priority' => AnnouncementPriority::Critical->value,
        ], $owner);

        $this->assertTrue($announcement->requires_acknowledgement);
    }
}
