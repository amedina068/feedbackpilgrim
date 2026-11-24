<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tags\CreateTag;
use App\Actions\Tags\DeleteTag;
use App\Actions\Tags\ListTags;
use App\Actions\Tags\UpdateTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends PublicApiController
{
    public function index(Request $request): JsonResponse
    {
        $tags = (new ListTags)($this->clientContext($request), $request->all());

        return response()->json($tags);
    }

    public function store(Request $request): JsonResponse
    {
        $tag = (new CreateTag)($this->clientContext($request), $request->all());

        return response()->json($tag, 201);
    }

    public function update(Request $request, int $tagId): JsonResponse
    {
        $tag = (new UpdateTag)($this->clientContext($request), [...$request->all(), 'id' => $tagId]);

        return response()->json($tag);
    }

    public function destroy(Request $request, int $tagId): JsonResponse
    {
        (new DeleteTag)($this->clientContext($request), ['id' => $tagId]);

        return response()->json(null, 204);
    }
}
