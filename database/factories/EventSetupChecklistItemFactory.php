<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventSetupChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSetupChecklistItem>
 */
class EventSetupChecklistItemFactory extends Factory
{
    protected $model = EventSetupChecklistItem::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'required' => false,
            'sequence' => fake()->numberBetween(0, 10),
            'completed' => false,
            'completed_at' => null,
            'source_key' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => [
            'required' => true,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'completed' => true,
            'completed_at' => now(),
        ]);
    }
}
