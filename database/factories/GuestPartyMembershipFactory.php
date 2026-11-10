<?php

namespace Database\Factories;

use App\Enums\GuestPartyStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestPartyMembership>
 */
class GuestPartyMembershipFactory extends Factory
{
    protected $model = GuestPartyMembership::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'event_id' => fn (array $attributes) => Guest::find($attributes['guest_id'])?->event_id ?? Event::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->numerify('##########'),
            'status' => GuestPartyStatus::Active,
        ];
    }

    public function forParty(Guest $party): static
    {
        return $this->state(fn () => [
            'guest_id' => $party->id,
            'event_id' => $party->event_id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => GuestPartyStatus::Inactive]);
    }

    public function removed(): static
    {
        return $this->state(fn () => ['status' => GuestPartyStatus::Removed]);
    }
}
