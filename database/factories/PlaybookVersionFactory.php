<?php

namespace Database\Factories;

use App\Models\Playbook;
use App\Models\PlaybookVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaybookVersion>
 */
class PlaybookVersionFactory extends Factory
{
    protected $model = PlaybookVersion::class;

    public function definition(): array
    {
        return [
            'playbook_id' => Playbook::factory(),
            'version' => 1,
            'config' => [
                'phases' => [
                    ['name' => 'Persiapan', 'sessions' => []],
                    ['name' => 'Acara Utama', 'sessions' => []],
                ],
                'checklist' => [
                    ['key' => 'venue_confirmed', 'label' => 'Konfirmasi venue', 'required' => true],
                ],
            ],
            'notes' => fake()->sentence(),
        ];
    }

    public function forPlaybook(Playbook $playbook, int $version = 1): static
    {
        return $this->state(fn () => [
            'playbook_id' => $playbook->id,
            'version' => $version,
        ]);
    }
}
