<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventStaffMembership>
 */
class EventStaffMembershipFactory extends Factory
{
    protected $model = EventStaffMembership::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'role' => 'guest_manager',
            'status' => 'active',
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
