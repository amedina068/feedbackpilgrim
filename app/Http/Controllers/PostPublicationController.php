<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostPublicationController extends Controller
{
    public function store(Request $request, $postId)
    {
        $post = Post::query()
            ->where([
                'client_id' => $request->attributes->get('fp_client_id'),
                'id' => $postId,
            ])
            ->firstOrFail();

        $post->update([
            'published_at' => now(),
        ]);

        return redirect("posts/{$post->id}")->with('success', 'Publication date updated successfully');
    }

    public function destroy(Request $request, $postId)
    {
        $post = Post::query()
            ->where([
                'client_id' => $request->attributes->get('fp_client_id'),
                'id' => $postId,
            ])
            ->firstOrFail();

        $post->update([
            'published_at' => null,
        ]);

        return redirect("posts/{$post->id}")->with('success', 'Publication date updated successfully');
    }
}
