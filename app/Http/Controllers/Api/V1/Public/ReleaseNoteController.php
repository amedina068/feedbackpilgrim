<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\ReleaseNotes\CreateReleaseNote;
use App\Actions\ReleaseNotes\GetReleaseNote;
use App\Actions\ReleaseNotes\ListReleaseNotes;
use App\Actions\ReleaseNotes\UpdateReleaseNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseNoteController extends PublicApiController
{
    public function index(Request $request): JsonResponse
    {
        $releaseNotes = (new ListReleaseNotes)($this->clientContext($request), $request->all());

        return response()->json($releaseNotes);
    }

    public function store(Request $request): JsonResponse
    {
        $releaseNote = (new CreateReleaseNote)($this->clientContext($request), $request->all());

        return response()->json($releaseNote, 201);
    }

    public function show(Request $request, $releaseNoteId): JsonResponse
    {
        $releaseNote = (new GetReleaseNote)($this->clientContext($request), ['id' => $releaseNoteId]);

        return response()->json($releaseNote);
    }

    public function update(Request $request, $releaseNoteId): JsonResponse
    {
        $releaseNote = (new UpdateReleaseNote)($this->clientContext($request), [...$request->all(), 'id' => $releaseNoteId]);

        return response()->json($releaseNote);
    }

    public function destroy(Request $request, $releaseNoteId): JsonResponse
    {
        return response()->json(['message' => 'Method not allowed'], 405);
    }
}
