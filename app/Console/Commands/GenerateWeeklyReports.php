<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportNotification;
use App\Services\WeeklyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateWeeklyReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fp:weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send the activity reports of the previous week';

    /**
     * Execute the console command.
     */
    public function handle(WeeklyReportService $reportService)
    {
        $weekStart = Carbon::now()->subWeek()->startOfWeek();

        $this->generateForAllClients($reportService, $weekStart);

        $this->sendEmailNotifications($weekStart);
    }

    private function generateForAllClients(WeeklyReportService $reportService, Carbon $weekStart)
    {
        Client::query()
            ->get()
            ->each(function ($client) use ($reportService, $weekStart) {
                $reportService->generateReportForClient($client, $weekStart);
            });
    }

    private function sendEmailNotifications(Carbon $weekStart)
    {
        $reports = WeeklyReport::query()
            ->where('week_start', $weekStart->toDateString())
            ->get()
            ->each(function ($report) {
                $users = User::query()->whereHas(
                    'clients',
                    fn ($query) => $query->where('client_id', $report->client_id)
                )
                    ->get()
                    ->each(fn ($user) => $user->notify(new WeeklyReportNotification($report)));
            });
    }
}
