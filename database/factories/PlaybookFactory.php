<?php

namespace Database\Factories;

use App\Enums\PlaybookStatus;
use App\Models\Organization;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Playbook>
 */
class PlaybookFactory extends Factory
{
    protected $model = Playbook::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'organization_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'event_type' => fake()->randomElement(['wedding', 'engagement', 'reception']),
            'days' => fake()->numberBetween(1, 3),
            'status' => PlaybookStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'organization_id' => null,
        ]);
    }

    public function forOrganization(?Organization $organization = null): static
    {
        return $this->state(fn () => [
            'organization_id' => $organization?->id ?? Organization::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => PlaybookStatus::Inactive,
        ]);
    }
}
