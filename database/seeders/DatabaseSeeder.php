<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            $this->call(DemoSeeder::class);
        } else {
            $this->command?->warn('Production environment detected: Demo accounts skipped. Run php artisan db:seed --class=DemoSeeder explicitly if needed.');
        }
    }
}
