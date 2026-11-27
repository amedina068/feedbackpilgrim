<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Models\Client;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportNotification;
use App\Services\WeeklyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateWeeklyReports extends Command
{
    use InteractsWithAgents;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fp:weekly
        {--json : Print one JSON object on stdout}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send the activity reports of the previous week';

    /**
     * Execute the console command.
     */
    public function handle(WeeklyReportService $reportService): int
    {
        $this->prepareAgentMode();

        $weekStart = Carbon::now()->subWeek()->startOfWeek();

        $this->generateForAllClients($reportService, $weekStart);

        $reports = $this->sendEmailNotifications($weekStart);

        return $this->respond(AgentExit::SUCCESS, [
            'week_start' => $weekStart->toDateString(),
            'reports' => $reports,
        ]);
    }

    private function generateForAllClients(WeeklyReportService $reportService, Carbon $weekStart): void
    {
        Client::query()
            ->get()
            ->each(function ($client) use ($reportService, $weekStart) {
                $reportService->generateReportForClient($client, $weekStart);
            });
    }

    /**
     * Returns the number of reports of the week. SQLite stores the `date`
     * cast as "Y-m-d 00:00:00", so the query compares the date part only.
     */
    private function sendEmailNotifications(Carbon $weekStart): int
    {
        return WeeklyReport::query()
            ->whereDate('week_start', $weekStart->toDateString())
            ->get()
            ->each(function ($report) {
                User::query()->whereHas(
                    'clients',
                    fn ($query) => $query->where('client_id', $report->client_id)
                )
                    ->get()
                    ->each(fn ($user) => $user->notify(new WeeklyReportNotification($report)));
            })
            ->count();
    }
}
