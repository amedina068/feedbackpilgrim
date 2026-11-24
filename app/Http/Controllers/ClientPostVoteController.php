<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientPostVoteController extends Controller
{
    use LogsActivity;

    public function store(Request $request, $postId)
    {
        $contact = Auth::guard('contact')->user();

        $client = $request->client;

        if ($client->id != $contact->client_id) {
            return abort(401);
        }

        $post = Post::query()
            ->where([
                'client_id' => $client->id,
                'id' => $postId,
            ])
            ->public()
            ->firstOrFail();

        $vote = $post->votes()->firstOrCreate([
            'contact_id' => $contact->id,
        ]);

        if (! $vote->wasRecentlyCreated) {
            return back()->with('info', 'You have already voted');
        }

        $this->logActivity($contact->client_id, $contact, $post, 'vote_created');

        return back()->with('success', 'Vote created');
    }

    public function destroy(Request $request, $postId, $voteId)
    {
        $contact = Auth::guard('contact')->user();

        $client = $request->client;

        if ($client->id != $contact->client_id) {
            return abort(401);
        }

        $post = Post::query()
            ->where([
                'client_id' => $client->id,
                'id' => $postId,
            ])
            ->firstOrFail();

        $post->votes()->where([
            'contact_id' => $contact->id,
        ])->delete();

        $this->logActivity($contact->client_id, $contact, $post, 'vote_deleted');

        return back()->with('success', 'Vote removed');
    }
}
