<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Traits\LogsActivity;
use App\Traits\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostCommentController extends Controller
{
    use LogsActivity, NotifiesUsers;

    public function index(Request $request, $postId)
    {
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'comments' => fn ($query) => $query->with(['contact:id,name,email', 'media']),
            ])
            ->findOrFail($postId);

        $comments = $post->comments;

        return view('post-comments.index', compact('comments', 'post'));
    }

    public function store(Request $request, $postId)
    {
        $user = Auth::user();

        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $contact = $user->adminContactFor($post->client_id);

        $comment = $post->comments()->create([
            'contact_id' => $contact->id,
            'content' => $request->input('content'),
        ]);

        $this->logActivity($request->attributes->get('fp_client_id'), $contact, $post, 'comment_created');

        $this->notifyUser($post, $comment);

        return back()->with('success', 'Comment created');
    }
}
