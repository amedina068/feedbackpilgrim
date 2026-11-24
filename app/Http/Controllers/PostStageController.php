<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Stage;
use App\Support\PostStageTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostStageController extends Controller
{
    /**
     * Drag-and-drop move from the kanban board: reassign stage + position and
     * return the refreshed board so the front end can remap it.
     */
    public function move(Request $request, $postId): JsonResponse
    {
        $request->validate([
            'stage_id' => [
                'required',
                'integer',
                Rule::exists('stages', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->where('id', $postId)
            ->firstOrFail();

        $stage = Stage::query()->find($request->stage_id);

        PostStageTransition::apply($post, $stage, $request->integer('position'));

        $stages = Stage::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('position')
            ->with(['posts' => fn ($query) => $query->withCount('votes')->orderBy('stage_position')])
            ->get();

        return response()->json(['data' => $stages]);
    }
}
