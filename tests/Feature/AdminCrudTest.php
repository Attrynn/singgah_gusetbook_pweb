<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_superadmin_can_create_event_with_schedules(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.events.store'), [
                'name' => 'Raka & Alya',
                'groom_name' => 'Raka',
                'bride_name' => 'Alya',
                'event_date' => now()->addMonth()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '14:00',
                'venue_name' => 'Gedung Serbaguna',
                'venue_address' => 'Jl. Melati No. 1',
                'status' => 'published',
                'schedules' => [
                    ['title' => 'Akad Nikah', 'start_time' => now()->addMonth()->setTime(10, 0)->toDateTimeString(), 'status' => 'planned'],
                    ['title' => 'Resepsi', 'start_time' => now()->addMonth()->setTime(12, 0)->toDateTimeString(), 'status' => 'planned'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $event = Event::where('name', 'Raka & Alya')->firstOrFail();

        $this->assertSame($admin->id, $event->created_by);
        $this->assertCount(2, $event->schedules);
        $this->assertDatabaseHas('event_schedules', ['event_id' => $event->id, 'title' => 'Akad Nikah']);
    }

    public function test_creating_a_guest_issues_an_invitation_token(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();

        $this->actingAs($admin)
            ->post(route('admin.guests.store'), [
                'event_id' => $event->id,
                'name' => 'Tamu Baru',
                'status' => 'active',
                'max_attendees' => 2,
            ])
            ->assertSessionHasNoErrors();

        $guest = Guest::where('name', 'Tamu Baru')->firstOrFail();

        $this->assertNotNull($guest->activeInvitationToken());
        $this->assertNotNull($guest->activeQrToken());
        $this->assertDatabaseHas('invitations', ['guest_id' => $guest->id]);
    }

    public function test_guest_export_returns_csv(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $this->makeGuest($event, ['name' => 'Tamu Ekspor']);

        $response = $this->actingAs($admin)->get(route('admin.guests.export', ['event_id' => $event->id]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('Tamu Ekspor', $response->streamedContent());
    }

    public function test_report_export_returns_csv(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['event_id' => $event->id, 'type' => 'summary']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_event_status_can_be_changed(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent(['status' => 'draft']);

        $this->actingAs($admin)
            ->post(route('admin.events.status', $event), ['status' => 'live'])
            ->assertSessionHasNoErrors();

        $this->assertSame('live', $event->fresh()->status->value);
    }

    public function test_settings_can_be_updated(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'app_name' => 'VOWSPACE Demo',
                'support_email' => 'halo@vowspace.test',
                'default_timezone' => 'Asia/Makassar',
                'guestbook_auto_approve' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('VOWSPACE Demo', AppSetting::get('app_name'));
        $this->assertSame('Asia/Makassar', AppSetting::get('default_timezone'));
    }

    public function test_owner_cannot_manage_guests(): void
    {
        $owner = User::factory()->owner()->create();
        $event = $this->makeEvent();

        $this->actingAs($owner)
            ->post(route('admin.guests.store'), [
                'event_id' => $event->id,
                'name' => 'Tidak Boleh',
                'status' => 'active',
                'max_attendees' => 1,
            ])
            ->assertForbidden();
    }
}
