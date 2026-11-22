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

    public function test_guest_export_without_event_returns_csv_for_all_events(): void
    {
        $admin = User::factory()->superadmin()->create();
        $first = $this->makeEvent();
        $second = $this->makeEvent();
        $this->makeGuest($first, ['name' => 'Tamu Pertama']);
        $this->makeGuest($second, ['name' => 'Tamu Kedua']);

        $response = $this->actingAs($admin)->get(route('admin.guests.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Tamu Pertama', $csv);
        $this->assertStringContainsString('Tamu Kedua', $csv);
        $this->assertStringContainsString('Event', $csv);
    }

    public function test_guest_export_applies_search_filter(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $this->makeGuest($event, ['name' => 'only-one-name']);
        $this->makeGuest($event, ['name' => 'other-guest']);

        $response = $this->actingAs($admin)->get(route('admin.guests.export', [
            'event_id' => $event->id,
            'search' => 'only-one-name',
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('only-one-name', $csv);
        $this->assertStringNotContainsString('other-guest', $csv);
    }

    public function test_admin_guests_index_export_link_preserves_all_guests_without_filter(): void
    {
        $admin = User::factory()->superadmin()->create();
        $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.guests.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.guests.export').'"', false);
        $response->assertDontSee(route('admin.guests.export').'?', false);
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

    public function test_report_export_without_event_id_uses_latest_event(): void
    {
        $admin = User::factory()->superadmin()->create();
        $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.reports.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_report_pdf_without_event_id_uses_latest_event(): void
    {
        $admin = User::factory()->superadmin()->create();
        $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.reports.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_event_status_can_be_changed(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent(['status' => 'draft']);

        $this->actingAs($admin)
            ->post(route('admin.events.status', $event), ['status' => 'published'])
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $event->fresh()->status->value);
    }

    public function test_invalid_event_status_transition_is_rejected(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent(['status' => 'draft']);

        $this->actingAs($admin)
            ->post(route('admin.events.status', $event), ['status' => 'live'])
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $event->fresh()->status->value);
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

    public function test_event_create_form_only_submits_integer_owner_ids(): void
    {
        $admin = User::factory()->superadmin()->create();
        User::factory()->owner()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.events.create'));

        $response->assertOk();
        // Owner selection must only submit integer ids via checkboxes. A stray
        // free-text "Cari owner..." input used to post an empty string into
        // owner_ids[] and fail with "owner_ids.0 must be an integer".
        $this->assertStringNotContainsString('type="text" name="owner_ids[]"', $response->getContent());
        $this->assertStringContainsString('type="checkbox" name="owner_ids[]"', $response->getContent());
    }

    public function test_event_edit_form_only_submits_integer_owner_ids(): void
    {
        $admin = User::factory()->superadmin()->create();
        User::factory()->owner()->create(['is_active' => true]);
        $event = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.edit', $event));

        $response->assertOk();
        $this->assertStringNotContainsString('type="text" name="owner_ids[]"', $response->getContent());
        $this->assertStringContainsString('type="checkbox" name="owner_ids[]"', $response->getContent());
    }
}
