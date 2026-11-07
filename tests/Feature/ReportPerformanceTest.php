<?php

namespace Tests\Feature;

use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class ReportPerformanceTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_event_stats_runs_a_bounded_number_of_queries(): void
    {
        $event = $this->makeEvent();

        // Add several guests so any per-row query pattern would be obvious.
        for ($i = 0; $i < 15; $i++) {
            $this->makeGuest($event);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ReportService::class)->eventStats($event);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Aggregates replaced ~20 per-metric queries. Keep a clear ceiling so a
        // regression to N+1 fails the suite.
        $this->assertLessThanOrEqual(12, $queryCount, "eventStats issued {$queryCount} queries.");
    }

    public function test_poll_results_do_not_query_per_option(): void
    {
        $event = $this->makeEvent();

        for ($i = 0; $i < 5; $i++) {
            $poll = $event->polls()->create([
                'question' => "Question {$i}",
                'is_active' => true,
            ]);

            for ($j = 0; $j < 5; $j++) {
                $poll->options()->create(['label' => "Option {$j}", 'sort_order' => $j]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ReportService::class)->pollResults($event);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $queryCount, "pollResults issued {$queryCount} queries.");
    }
}
