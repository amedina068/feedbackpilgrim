<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ReleaseNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientHomeController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->client;

        $releaseNotes = ReleaseNote::query()
            ->where('client_id', $client->id)
            ->with(['comments' => fn ($query) => $query->latest()->with(['contact'])])
            ->published()
            ->take(4)
            ->latest('id')
            ->get();

        $posts = Post::query()
            ->leftJoin('votes', function ($join) {
                $join->on('posts.id', '=', 'votes.votable_id')
                    ->where('votes.votable_type', '=', Post::class);
            })
            ->where('client_id', $client->id)
            ->public()
            ->select(['posts.*', DB::raw('COUNT(votes.id) as votes_count')])
            ->votedByContact(Auth::guard('contact')->id())
            ->groupBy('posts.id')
            ->with([
                'contact',
                'comments' => fn ($query) => $query->latest()->with(['contact']),
            ])
            ->take(5)
            ->latest('posts.id')
            ->get();

        return view('client-home.index', compact('client', 'posts', 'releaseNotes'));
    }
}
