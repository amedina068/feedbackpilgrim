<?php

namespace App\Services;

use App\Traits\MetricsCalculations;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardMetricsService
{
    use MetricsCalculations;

    public function getContactLoginsByDay(int $clientId, int $days = 7): array
    {
        $cacheKey = "dashboard_logins_by_day_{$clientId}_{$days}";

        return Cache::remember($cacheKey, 300, function () use ($clientId, $days) {
            $endDate = Carbon::now();
            $startDate = $endDate->copy()->subDays($days - 1);

            $loginData = Activity::where('client_id', $clientId)
                ->where('description', 'login')
                ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->selectRaw('DATE(created_at) as date, COUNT(DISTINCT causer_id) as logins')
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $loginsByDay = [];
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dayStr = $date->format('Y-m-d');
                $logins = $loginData->get($dayStr)?->logins ?? 0;

                $loginsByDay[] = [
                    'date' => $dayStr,
                    'day_name' => $date->format('l'),
                    'short_date' => $date->format('M j'),
                    'logins' => $logins,
                ];
            }

            return $loginsByDay;
        });
    }

    public function getTopContactsByLogins(int $clientId, int $days = 7, int $limit = 10): Collection
    {
        $cacheKey = "dashboard_top_logins_{$clientId}_{$days}_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($clientId, $days, $limit) {
            [$startDate, $endDate] = $this->getTimeRange($days);
            $loginCounts = $this->getLoginCounts($clientId, $startDate, $endDate, $limit);

            return $this->getContactsWithLoginCounts($clientId, $loginCounts);
        });
    }

    public function getTopContactsByPostsPublished(int $clientId, int $days = 7, int $limit = 10): Collection
    {
        $cacheKey = "dashboard_top_posts_{$clientId}_{$days}_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($clientId, $days, $limit) {
            [$startDate, $endDate] = $this->getTimeRange($days);

            return $this->getContactsWithMostPostsPublished($clientId, $startDate, $endDate, $limit);
        });
    }

    public function getTopContactsByActivity(int $clientId, int $days = 7, int $limit = 10): Collection
    {
        $cacheKey = "dashboard_top_activity_{$clientId}_{$days}_{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($clientId, $days, $limit) {
            [$startDate, $endDate] = $this->getTimeRange($days);

            return $this->getContactsWithMostActivity($clientId, $startDate, $endDate, $limit);
        });
    }

    public function getDashboardSummary(int $clientId, int $days = 7): array
    {
        $cacheKey = "dashboard_summary_{$clientId}_{$days}";

        return Cache::remember($cacheKey, 300, function () use ($clientId, $days) {
            [$startDate, $endDate] = $this->getTimeRange($days);

            $summary = DB::select('
                SELECT 
                    (SELECT COUNT(*) FROM contacts WHERE client_id = ?) as total_contacts,
                    (SELECT COUNT(DISTINCT contact_id) 
                     FROM (
                         SELECT contact_id FROM posts WHERE client_id = ? AND created_at BETWEEN ? AND ?
                         UNION
                         SELECT contact_id FROM comments 
                         WHERE contact_id IN (SELECT id FROM contacts WHERE client_id = ?) 
                         AND created_at BETWEEN ? AND ?
                         UNION
                         SELECT contact_id FROM votes 
                         WHERE contact_id IN (SELECT id FROM contacts WHERE client_id = ?) 
                         AND created_at BETWEEN ? AND ?
                     ) as active_contacts
                    ) as active_contacts,
                    (SELECT COUNT(*) FROM posts WHERE client_id = ? AND created_at BETWEEN ? AND ?) as posts_created,
                    (SELECT COUNT(*) FROM posts WHERE client_id = ? AND published_at BETWEEN ? AND ?) as posts_published,
                    (SELECT COUNT(*) FROM comments c
                     JOIN contacts ct ON c.contact_id = ct.id 
                     WHERE ct.client_id = ? AND c.created_at BETWEEN ? AND ?) as comments_created
            ', [
                $clientId, // total_contacts
                $clientId, $startDate, $endDate, // active_contacts posts
                $clientId, $startDate, $endDate, // active_contacts comments
                $clientId, $startDate, $endDate, // active_contacts votes
                $clientId, $startDate, $endDate, // posts_created
                $clientId, $startDate, $endDate, // posts_published
                $clientId, $startDate, $endDate,  // comments_created
            ])[0];

            $totalContacts = $summary->total_contacts;
            $activeContacts = $summary->active_contacts;

            return [
                'total_contacts' => $totalContacts,
                'active_contacts' => $activeContacts,
                'posts_created' => $summary->posts_created,
                'posts_published' => $summary->posts_published,
                'comments_created' => $summary->comments_created,
                'activity_rate' => $totalContacts > 0 ? round(($activeContacts / $totalContacts) * 100, 1) : 0,
            ];
        });
    }
}
