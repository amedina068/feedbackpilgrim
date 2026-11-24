<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostNoteController extends Controller
{
    public function edit(Request $request, $postId): View
    {
        $post = $this->post($request, $postId);

        return view('post-notes.edit', compact('post'));
    }

    public function update(Request $request, $postId): RedirectResponse
    {
        $request->validate([
            'notes' => ['string', 'nullable'],
        ]);

        $post = $this->post($request, $postId);

        $post->update($request->only('notes'));

        return redirect("posts/{$post->id}")->with('success', 'Notes updated successfully');
    }

    /**
     * The post of the URL, in the current client only.
     */
    private function post(Request $request, mixed $postId): Post
    {
        return Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);
    }
}
