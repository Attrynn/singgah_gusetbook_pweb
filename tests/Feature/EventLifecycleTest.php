<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\User;
use App\Services\EventLifecycleService;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_happy_path_transitions_are_allowed_and_timestamped(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Draft, 'published_at' => null]);
        $admin = User::factory()->superadmin()->create();
        $lifecycle = app(EventLifecycleService::class);

        $event = $lifecycle->transition($event, EventStatus::Published, $admin);
        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertNotNull($event->published_at);

        $event = $lifecycle->transition($event, EventStatus::Live, $admin);
        $this->assertSame(EventStatus::Live, $event->status);

        $event = $lifecycle->transition($event, EventStatus::Completed, $admin);
        $this->assertSame(EventStatus::Completed, $event->status);

        $event = $lifecycle->transition($event, EventStatus::Archived, $admin);
        $this->assertSame(EventStatus::Archived, $event->status);
        $this->assertNotNull($event->archived_at);
    }

    public function test_forbidden_transitions_are_rejected(): void
    {
        $admin = User::factory()->superadmin()->create();
        $lifecycle = app(EventLifecycleService::class);

        $this->assertFalse($lifecycle->canTransition(EventStatus::Draft, EventStatus::Live));
        $this->assertFalse($lifecycle->canTransition(EventStatus::Archived, EventStatus::Draft));
        $this->assertFalse($lifecycle->canTransition(EventStatus::Completed, EventStatus::Published));
        $this->assertTrue($lifecycle->canTransition(EventStatus::Published, EventStatus::Draft));

        $event = $this->makeEvent(['status' => EventStatus::Draft]);

        $this->expectException(ValidationException::class);
        $lifecycle->transition($event, EventStatus::Live, $admin);
    }

    public function test_admin_http_status_endpoint_rejects_forbidden_transition(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent(['status' => EventStatus::Draft]);

        $this->actingAs($admin)
            ->post(route('admin.events.status', $event), ['status' => 'live'])
            ->assertSessionHasErrors('status');

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_event_update_cannot_bypass_transition_rules(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent(['status' => EventStatus::Draft]);

        $payload = [
            'name' => $event->name,
            'groom_name' => $event->groom_name,
            'bride_name' => $event->bride_name,
            'event_date' => $event->event_date->toDateString(),
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'status' => 'live',
        ];

        try {
            app(EventService::class)->update($event, $payload, $admin);
            $this->fail('Expected validation exception for forbidden transition.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }
}
