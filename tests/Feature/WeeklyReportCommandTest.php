<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\WeeklyReport;
use App\Services\WeeklyReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_monday_run_reports_the_previous_week(): void
    {
        $client = Client::factory()->create();

        $this->mock(WeeklyReportService::class)
            ->shouldReceive('generateReportForClient')
            ->once()
            ->withArgs(fn (Client $reported, Carbon $weekStart) => $reported->is($client)
                && $weekStart->toDateTimeString() === '2026-11-09 00:00:00')
            ->andReturn(new WeeklyReport);

        $this->travelTo('2026-11-16 09:00:00');

        $this->artisan('fp:weekly')->assertSuccessful();
    }
}
