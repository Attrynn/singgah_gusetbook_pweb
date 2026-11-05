<?php

namespace Database\Factories;

use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->numerify('##########'),
            'status' => GuestStatus::Active,
            'max_attendees' => fake()->numberBetween(1, 4),
            'rsvp_status' => RsvpStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['rsvp_status' => RsvpStatus::Confirmed]);
    }

    public function declined(): static
    {
        return $this->state(fn () => ['rsvp_status' => RsvpStatus::Declined]);
    }
}
