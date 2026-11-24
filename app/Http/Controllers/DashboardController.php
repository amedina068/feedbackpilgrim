<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetricsService $metricsService)
    {
        $clientId = $request->attributes->get('fp_client_id');

        $latestActivities = Activity::query()
            ->where('client_id', $clientId)
            ->whereIn('description', [
                'comment_created',
                'vote_created',
                'post_created',
            ])
            ->latest()
            ->take(5)
            ->with(['causer'])
            ->get();

        $latestPosts = Post::query()
            ->where('client_id', $clientId)
            ->latest()
            ->with(['tags', 'contact:id,name,top_customer'])
            ->withCount(['votes', 'comments'])
            ->where('status', '!=', 'completed')
            ->take(5)
            ->get();

        $latestContacts = Contact::query()
            ->where('client_id', $clientId)
            ->withCount(['posts', 'comments', 'votes'])
            ->whereNull('user_id')
            ->latest()
            ->take(5)
            ->get();

        $latestComments = Comment::query()
            ->where('contacts.client_id', $clientId)
            ->join('contacts', 'comments.contact_id', 'contacts.id')
            ->with(['contact', 'commentable'])
            ->latest()
            ->select(['comments.*'])
            ->take(5)
            ->get();

        $key = Client::query()->whereKey($clientId)->value('publishable_key');

        $loginsByDay = $metricsService->getContactLoginsByDay($clientId, 7);
        $topContactsByLogins = $metricsService->getTopContactsByLogins($clientId, 7, 5);
        $topContactsByPosts = $metricsService->getTopContactsByPostsPublished($clientId, 7, 5);
        $topContactsByActivity = $metricsService->getTopContactsByActivity($clientId, 7, 5);
        $dashboardSummary = $metricsService->getDashboardSummary($clientId, 7);

        return view('dashboard', compact(
            'latestActivities',
            'latestPosts',
            'latestContacts',
            'latestComments',
            'key',
            'loginsByDay',
            'topContactsByLogins',
            'topContactsByPosts',
            'topContactsByActivity',
            'dashboardSummary'
        ));
    }
}
