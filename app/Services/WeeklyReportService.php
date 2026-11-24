<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use App\Models\WeeklyReport;
use App\Traits\MetricsCalculations;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class WeeklyReportService
{
    use MetricsCalculations;

    public function generateReportForClient(Client $client, ?Carbon $weekStart = null): WeeklyReport
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $metrics = $this->collectMetrics($client, $weekStart, $weekEnd);

        return WeeklyReport::updateOrCreate(
            [
                'client_id' => $client->id,
                'week_start' => $weekStart->toDateString(),
            ],
            [
                'week_end' => $weekEnd->toDateString(),
                'metrics' => $metrics,
            ]
        );
    }

    public function generateReportsForAllClients(?Carbon $weekStart = null): Collection
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        $reports = collect();

        Client::chunk(100, function ($clients) use ($weekStart, $reports) {
            foreach ($clients as $client) {
                $reports->push($this->generateReportForClient($client, $weekStart));
            }
        });

        return $reports;
    }

    private function collectMetrics(Client $client, Carbon $weekStart, Carbon $weekEnd): array
    {
        // Get login counts and contact details
        $loginCounts = $this->getLoginCounts($client->id, $weekStart, $weekEnd, 10);
        $contactsWithLogins = $this->getContactsWithLoginCounts($client->id, $loginCounts)->toArray();

        return [
            'contacts_with_most_logins' => $contactsWithLogins,
            'contacts_with_most_posts' => $this->getContactsWithMostPosts($client->id, $weekStart, $weekEnd, 10)->toArray(),
            'contacts_with_most_comments' => $this->getContactsWithMostComments($client->id, $weekStart, $weekEnd, 10)->toArray(),
            'posts_created' => $this->getPostsCreatedCount($client->id, $weekStart, $weekEnd),
            'posts_completed' => $this->getPostsCompletedCount($client->id, $weekStart, $weekEnd),
            'total_logins' => $this->getTotalLoginCount($client->id, $weekStart, $weekEnd),
            'active_contacts' => $this->getActiveContactsCount($client->id, $weekStart, $weekEnd),
        ];
    }
}
