<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Posts\CreatePost;
use App\Actions\Posts\GetPost;
use App\Actions\Posts\ListPosts;
use App\Actions\Posts\UpdatePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends PublicApiController
{
    public function index(Request $request): JsonResponse
    {
        $posts = (new ListPosts)($this->clientContext($request), $request->all());

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $post = (new CreatePost)($this->clientContext($request), $request->all());

        return response()->json($post, 201);
    }

    public function show(Request $request, $postId): JsonResponse
    {
        $post = (new GetPost)($this->clientContext($request), ['id' => $postId]);

        return response()->json($post);
    }

    public function update(Request $request, $postId): JsonResponse
    {
        $post = (new UpdatePost)($this->clientContext($request), [...$request->all(), 'id' => $postId]);

        return response()->json($post);
    }

    public function destroy(Request $request, $postId): JsonResponse
    {
        return response()->json(['message' => 'Method not allowed'], 405);
    }
}
