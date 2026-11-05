<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $groom = fake()->firstNameMale();
        $bride = fake()->firstNameFemale();
        $name = "{$groom} & {$bride}";

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'groom_name' => $groom,
            'bride_name' => $bride,
            'groom_profile' => fake()->paragraph(),
            'bride_profile' => fake()->paragraph(),
            'couple_description' => fake()->paragraph(),
            'event_date' => now()->addDays(fake()->numberBetween(10, 120))->toDateString(),
            'start_time' => '10:00',
            'end_time' => '14:00',
            'venue_name' => fake()->company(),
            'venue_address' => fake()->address(),
            'google_maps_url' => 'https://maps.google.com/?q='.urlencode(fake()->address()),
            'dress_code' => 'Formal — soft neutral tones',
            'description' => fake()->paragraph(),
            'status' => EventStatus::Published,
            'rsvp_enabled' => true,
            'guestbook_enabled' => true,
            'gallery_enabled' => true,
            'gift_enabled' => true,
            'live_wall_enabled' => false,
            'timezone' => 'Asia/Jakarta',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft, 'published_at' => null]);
    }

    public function live(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Live, 'published_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Completed, 'event_date' => now()->subDays(20)->toDateString()]);
    }
}
