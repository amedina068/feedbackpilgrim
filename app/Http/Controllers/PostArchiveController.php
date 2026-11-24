<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostArchiveController extends Controller
{
    public function store(Request $request, $postId)
    {
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);

        $post->update([
            'archived_at' => now(),
        ]);

        return redirect("posts/{$post->id}")->with('success', 'Post archived successfully');
    }

    public function destroy(Request $request, $postId)
    {
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);

        $post->update([
            'archived_at' => null,
        ]);

        return redirect("posts/{$post->id}")->with('success', 'Post unarchived successfully');
    }
}
