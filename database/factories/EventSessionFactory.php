<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSession>
 */
class EventSessionFactory extends Factory
{
    protected $model = EventSession::class;

    public function definition(): array
    {
        $date = now()->addDays(fake()->numberBetween(10, 60));

        return [
            'event_id' => Event::factory(),
            'phase_id' => null,
            'name' => fake()->words(2, true),
            'session_date' => $date->toDateString(),
            'start_time' => $date->copy()->setTime(10, 0),
            'end_time' => $date->copy()->setTime(12, 0),
            'timezone' => 'Asia/Jakarta',
            'sequence' => fake()->numberBetween(0, 5),
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id,
        ]);
    }

    public function forPhase(EventPhase $phase): static
    {
        return $this->state(fn () => [
            'event_id' => $phase->event_id,
            'phase_id' => $phase->id,
        ]);
    }
}
