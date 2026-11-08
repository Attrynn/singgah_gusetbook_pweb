<?php

namespace Database\Factories;

use App\Enums\RunOfShowStatus;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventRunOfShowItem;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRunOfShowItem>
 */
class EventRunOfShowItemFactory extends Factory
{
    protected $model = EventRunOfShowItem::class;

    public function definition(): array
    {
        $start = now()->addHour();

        return [
            'event_id' => Event::factory(),
            'event_session_id' => null,
            'phase_id' => null,
            'title' => fake()->sentence(3),
            'internal_description' => fake()->optional()->sentence(),
            'planned_start' => $start,
            'planned_end' => $start->copy()->addHour(),
            'actual_start' => null,
            'actual_end' => null,
            'status' => RunOfShowStatus::Pending,
            'sequence' => fake()->numberBetween(0, 10),
            'pic_staff_membership_id' => null,
            'internal_notes' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id,
        ]);
    }

    public function forSession(EventSession $session): static
    {
        return $this->state(fn () => [
            'event_id' => $session->event_id,
            'event_session_id' => $session->id,
        ]);
    }

    public function forPhase(EventPhase $phase): static
    {
        return $this->state(fn () => [
            'event_id' => $phase->event_id,
            'phase_id' => $phase->id,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => RunOfShowStatus::InProgress,
            'actual_start' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RunOfShowStatus::Completed,
            'actual_start' => now()->subHour(),
            'actual_end' => now(),
        ]);
    }

    public function delayed(): static
    {
        return $this->state(fn () => [
            'status' => RunOfShowStatus::Delayed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => RunOfShowStatus::Cancelled,
        ]);
    }
}
