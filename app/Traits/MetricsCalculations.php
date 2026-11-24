<?php

namespace App\Traits;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

trait MetricsCalculations
{
    /**
     * Get login counts from activity log for a given time period
     */
    protected function getLoginCounts(int $clientId, Carbon $startDate, Carbon $endDate, ?int $limit = null): Collection
    {
        $query = Activity::where('client_id', $clientId)
            ->where('description', 'login')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('causer_id, COUNT(*) as login_count')
            ->whereNotNull('causer_id')
            ->groupBy('causer_id')
            ->orderByDesc('login_count');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->keyBy('causer_id');
    }

    /**
     * Get total login count for a time period
     */
    protected function getTotalLoginCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Activity::where('client_id', $clientId)
            ->where('description', 'login')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get unique login count (distinct users) for a time period
     */
    protected function getUniqueLoginCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Activity::where('client_id', $clientId)
            ->where('description', 'login')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('causer_id')
            ->count('causer_id');
    }

    /**
     * Get contacts with their login counts, enriched with contact details
     */
    protected function getContactsWithLoginCounts(int $clientId, Collection $loginCounts): Collection
    {
        if ($loginCounts->isEmpty()) {
            return collect();
        }

        return Contact::where('client_id', $clientId)
            ->whereIn('id', $loginCounts->keys())
            ->get()
            ->map(function ($contact) use ($loginCounts) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'avatar' => $contact->avatar,
                    'login_count' => $loginCounts[$contact->id]->login_count,
                    'top_customer' => $contact->top_customer,
                ];
            })
            ->sortByDesc('login_count')
            ->values();
    }

    /**
     * Get posts created count for a time period
     */
    protected function getPostsCreatedCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Post::where('client_id', $clientId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get posts completed count for a time period
     */
    protected function getPostsCompletedCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Post::where('client_id', $clientId)
            ->where('status', Post::STATUS_COMPLETED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get posts published count for a time period
     */
    protected function getPostsPublishedCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Post::where('client_id', $clientId)
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get comments created count for a time period
     */
    protected function getCommentsCreatedCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Comment::whereHas('contact', function ($query) use ($clientId) {
            $query->where('client_id', $clientId);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get contacts with most posts published in a time period
     */
    protected function getContactsWithMostPostsPublished(int $clientId, Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        $published = fn ($query) => $query->whereNotNull('published_at')
            ->whereBetween('published_at', [$startDate, $endDate]);

        return Contact::where('client_id', $clientId)
            ->whereHas('posts', $published)
            ->withCount(['posts as published_posts_count' => $published])
            ->orderByDesc('published_posts_count')
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'avatar' => $contact->avatar,
                    'published_posts_count' => $contact->published_posts_count,
                    'top_customer' => $contact->top_customer,
                ];
            });
    }

    /**
     * Get contacts with most posts created in a time period
     */
    protected function getContactsWithMostPosts(int $clientId, Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        $created = fn ($query) => $query->whereBetween('created_at', [$startDate, $endDate]);

        return Contact::where('client_id', $clientId)
            ->whereHas('posts', $created)
            ->withCount(['posts as posts_count' => $created])
            ->orderByDesc('posts_count')
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'avatar' => $contact->avatar,
                    'posts_count' => $contact->posts_count,
                    'top_customer' => $contact->top_customer,
                ];
            });
    }

    /**
     * Get contacts with most comments in a time period
     */
    protected function getContactsWithMostComments(int $clientId, Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        $created = fn ($query) => $query->whereBetween('created_at', [$startDate, $endDate]);

        return Contact::where('client_id', $clientId)
            ->whereHas('comments', $created)
            ->withCount(['comments as comments_count' => $created])
            ->orderByDesc('comments_count')
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'avatar' => $contact->avatar,
                    'comments_count' => $contact->comments_count,
                    'top_customer' => $contact->top_customer,
                ];
            });
    }

    /**
     * Get total contacts count for a client
     */
    protected function getTotalContactsCount(int $clientId): int
    {
        return Contact::where('client_id', $clientId)->count();
    }

    /**
     * Get active contacts count (contacts with any activity in time period)
     */
    protected function getActiveContactsCount(int $clientId, Carbon $startDate, Carbon $endDate): int
    {
        return Contact::where('client_id', $clientId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereHas('posts', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })->orWhereHas('comments', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })->orWhereHas('votes', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->count();
    }

    /**
     * Get contacts with most total activity (posts + comments + votes)
     */
    protected function getContactsWithMostActivity(int $clientId, Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        $results = DB::select('
            SELECT 
                c.id,
                c.name,
                c.email,
                c.avatar,
                c.top_customer,
                COALESCE(p.posts_count, 0) as posts_count,
                COALESCE(cm.comments_count, 0) as comments_count,
                COALESCE(v.votes_count, 0) as votes_count,
                (COALESCE(p.posts_count, 0) + COALESCE(cm.comments_count, 0) + COALESCE(v.votes_count, 0)) as total_activity
            FROM contacts c
            LEFT JOIN (
                SELECT contact_id, COUNT(*) as posts_count 
                FROM posts 
                WHERE client_id = ? AND created_at BETWEEN ? AND ?
                GROUP BY contact_id
            ) p ON c.id = p.contact_id
            LEFT JOIN (
                SELECT contact_id, COUNT(*) as comments_count 
                FROM comments 
                WHERE created_at BETWEEN ? AND ?
                GROUP BY contact_id
            ) cm ON c.id = cm.contact_id
            LEFT JOIN (
                SELECT contact_id, COUNT(*) as votes_count 
                FROM votes 
                WHERE created_at BETWEEN ? AND ?
                GROUP BY contact_id
            ) v ON c.id = v.contact_id
            WHERE c.client_id = ?
            AND (p.contact_id IS NOT NULL OR cm.contact_id IS NOT NULL OR v.contact_id IS NOT NULL)
            ORDER BY total_activity DESC
            LIMIT ?
        ', [
            $clientId, $startDate, $endDate, // posts
            $startDate, $endDate, // comments
            $startDate, $endDate, // votes
            $clientId, // contacts
            $limit,
        ]);

        return collect($results)->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'avatar' => $contact->avatar,
                'posts_count' => (int) $contact->posts_count,
                'comments_count' => (int) $contact->comments_count,
                'votes_count' => (int) $contact->votes_count,
                'total_activity' => (int) $contact->total_activity,
                'top_customer' => (bool) $contact->top_customer,
            ];
        });
    }

    /**
     * Calculate time range for metrics (start and end dates)
     */
    protected function getTimeRange(int $days = 7): array
    {
        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        return [$startDate, $endDate];
    }
}
