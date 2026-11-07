<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_local_time_is_converted_to_utc(): void
    {
        $event = Event::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'event_date' => '2026-06-01',
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $this->assertSame('2026-06-01 03:00:00', $event->startsAtUtc()?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-01 07:00:00', $event->endsAtUtc()?->format('Y-m-d H:i:s'));
        $this->assertSame('10:00', $event->localStartsAt()?->format('H:i'));
    }

    public function test_dst_timezones_use_the_correct_offset_for_each_date(): void
    {
        $summer = Event::factory()->create([
            'timezone' => 'America/New_York',
            'event_date' => '2026-03-08',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $winter = Event::factory()->create([
            'timezone' => 'America/New_York',
            'event_date' => '2026-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        // 2026-03-08 is after the US DST switch (EDT, UTC-4); January is EST (UTC-5).
        $this->assertSame('2026-03-08 14:00:00', $summer->startsAtUtc()?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-15 15:00:00', $winter->startsAtUtc()?->format('Y-m-d H:i:s'));
    }

    public function test_invalid_timezone_falls_back_to_application_timezone(): void
    {
        $event = Event::factory()->create([
            'timezone' => 'Not/AZone',
            'event_date' => '2026-06-01',
            'start_time' => '10:00',
        ]);

        $this->assertSame(config('app.timezone'), $event->timezoneName());
    }
}
