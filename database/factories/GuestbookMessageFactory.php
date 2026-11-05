<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Models\Event;
use App\Models\GuestbookMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestbookMessage>
 */
class GuestbookMessageFactory extends Factory
{
    protected $model = GuestbookMessage::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'sender_name' => fake()->name(),
            'message' => fake()->paragraph(),
            'emoji' => fake()->randomElement(['🤍', '✨', '💐', '🕊️', '💍']),
            'visibility' => MessageVisibility::Public,
            'status' => MessageStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => MessageStatus::Approved, 'reviewed_at' => now()]);
    }
}
