<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostVoteController extends Controller
{
    public function index(Request $request, $postId): View
    {
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'votes' => fn ($query) => $query->with(['contact:id,name']),
            ])
            ->findOrFail($postId);

        $votes = $post->votes;

        $contacts = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->whereNotIn('id', $votes->pluck('contact_id')->filter()->all())
            ->orderBy('name')
            ->get();

        return view('post-votes.index', compact('votes', 'post', 'contacts'));
    }

    public function store(Request $request, $postId): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);

        if ($post->votes()->where('contact_id', $validated['contact_id'])->exists()) {
            return back()->withErrors(['contact_id' => 'That contact has already voted on this post.']);
        }

        $post->votes()->create(['contact_id' => $validated['contact_id']]);

        return back()->with('success', 'Vote added on behalf of the contact.');
    }
}
