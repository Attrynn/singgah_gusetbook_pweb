<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventOwnerMembership>
 */
class EventOwnerMembershipFactory extends Factory
{
    protected $model = EventOwnerMembership::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'role_label' => 'bride',
            'status' => 'active',
        ];
    }

    public function groom(): static
    {
        return $this->state(fn () => ['role_label' => 'groom']);
    }

    public function bride(): static
    {
        return $this->state(fn () => ['role_label' => 'bride']);
    }

    public function partner(): static
    {
        return $this->state(fn () => ['role_label' => 'partner']);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
