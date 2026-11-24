<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReleaseNoteResource;
use App\Models\ReleaseNote;
use Illuminate\Http\Request;

class ReleaseNoteController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->client;

        $releaseNotes = ReleaseNote::query()
            ->client($client->id)
            ->latest('published_at')
            ->published()
            ->get();

        return ReleaseNoteResource::collection($releaseNotes);

        return response()->json($releaseNotes);
    }
}
