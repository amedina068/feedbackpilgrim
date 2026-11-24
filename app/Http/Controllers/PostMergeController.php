<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\PostMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostMergeController extends Controller
{
    public function store(Request $request, $postId): RedirectResponse
    {
        $validated = $request->validate([
            'target_post_id' => [
                'required',
                'integer',
                'different:'.$postId,
                Rule::exists('posts', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        $source = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);

        $target = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($validated['target_post_id']);

        PostMerger::merge($source, $target);

        return redirect("/posts/{$target->id}")->with('success', 'Post merged successfully.');
    }
}
