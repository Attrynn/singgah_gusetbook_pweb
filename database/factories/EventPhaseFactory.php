<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPhase>
 */
class EventPhaseFactory extends Factory
{
    protected $model = EventPhase::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Persiapan', 'Akad', 'Resepsi']),
            'sequence' => fake()->numberBetween(0, 5),
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id,
        ]);
    }
}
