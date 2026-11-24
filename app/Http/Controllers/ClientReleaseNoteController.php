<?php

namespace App\Http\Controllers;

use App\Models\ReleaseNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientReleaseNoteController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->client;

        $releaseNotes = ReleaseNote::query()
            ->withCount(['votes', 'comments'])
            ->where('client_id', $client->id)
            ->published()
            ->votedByContact(Auth::guard('contact')->id())
            ->with(['comments' => fn ($query) => $query->latest()->with(['contact', 'media'])])
            ->latest()
            ->paginate();

        return view('client-release-notes.index', compact('client', 'releaseNotes'));
    }

    public function show(Request $request, $releaseNoteSlug)
    {
        $client = $request->client;

        $releaseNote = ReleaseNote::query()
            ->where([
                'client_id' => $client->id,
                'slug' => $releaseNoteSlug,
            ])
            ->published()
            ->withCount(['votes'])
            ->with(['votes' => fn ($query) => $query->take(5)])
            ->votedByContact(Auth::guard('contact')->id())
            ->with(['comments' => fn ($query) => $query->latest()->with(['contact', 'media'])])
            ->firstOrFail();

        return view('client-release-notes.show', compact('client', 'releaseNote'));
    }
}
