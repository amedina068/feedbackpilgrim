<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Stage;
use App\Support\PostStageTransition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostStatusController extends Controller
{
    public function update(Request $request, $postId)
    {
        $request->validate([
            'stage_id' => [
                'required',
                'integer',
                Rule::exists('stages', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->where('id', $postId)
            ->firstOrFail();

        $stage = Stage::query()->find($request->stage_id);

        PostStageTransition::apply($post, $stage);

        return redirect("posts/{$post->id}")->with('success', 'Post status updated successfully');
    }
}
