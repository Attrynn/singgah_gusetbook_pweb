<?php

namespace Database\Factories;

use App\Enums\SetupStatus;
use App\Models\Event;
use App\Models\EventSetupConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSetupConfig>
 */
class EventSetupConfigFactory extends Factory
{
    protected $model = EventSetupConfig::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'playbook_id' => null,
            'playbook_version_id' => null,
            'source_event_id' => null,
            'config_snapshot' => null,
            'setup_status' => SetupStatus::NotStarted,
            'primary_pic_staff_membership_id' => null,
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn () => [
            'event_id' => $event->id,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'setup_status' => SetupStatus::InProgress,
        ]);
    }

    public function readyForReview(): static
    {
        return $this->state(fn () => [
            'setup_status' => SetupStatus::ReadyForReview,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'setup_status' => SetupStatus::Ready,
        ]);
    }
}
