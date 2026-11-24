<?php

namespace App\Http\Controllers;

use App\Actions\ReleaseNotes\UpdateReleaseNote;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Support\ClientContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReleaseNoteController extends Controller
{
    /**
     * The sorts that the list accepts, as column and direction.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'created_at__DESC' => ['created_at', 'desc'],
        'created_at__ASC' => ['created_at', 'asc'],
        'votes_count__DESC' => ['votes_count', 'desc'],
        'votes_count__ASC' => ['votes_count', 'asc'],
    ];

    public function index(Request $request)
    {
        [$sortColumn, $sortDirection] = self::SORTS[$request->query('sort_by')] ?? self::SORTS['created_at__DESC'];
        $tagIds = array_filter((array) $request->query('tags', []), 'is_numeric');

        $releaseNotes = ReleaseNote::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->when($tagIds, fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereIn('tags.id', $tagIds)))
            ->with('tags')
            ->withCount('votes')
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate()
            ->withQueryString();
        $tags = Tag::query()->where('client_id', $request->attributes->get('fp_client_id'))->orderBy('title')->get();

        return view('release-notes.index', compact('releaseNotes', 'tags'));
    }

    public function show(Request $request, $releaseNoteId)
    {
        $releaseNote = ReleaseNote::query()->where('client_id', $request->attributes->get('fp_client_id'))->findOrFail($releaseNoteId);

        return view('release-notes.show', compact('releaseNote'));
    }

    public function create()
    {
        return view('release-notes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
        ]);

        $releaseNote = ReleaseNote::create($request->merge([
            'client_id' => $request->attributes->get('fp_client_id'),
            'user_id' => Auth::id(),
        ])->only('title', 'content', 'client_id', 'user_id', 'published_at'));

        return redirect("/release-notes/{$releaseNote->id}")->with('success', 'Release note created');
    }

    public function edit(Request $request, $releaseNoteId)
    {
        $releaseNote = ReleaseNote::query()->where('client_id', $request->attributes->get('fp_client_id'))->findOrFail($releaseNoteId);

        return view('release-notes.edit', compact('releaseNote'));
    }

    public function update(Request $request, UpdateReleaseNote $updateReleaseNote, $releaseNoteId)
    {
        $releaseNote = $updateReleaseNote(ClientContext::fromDashboard($request), [
            ...$request->only('title', 'content'),
            'id' => $releaseNoteId,
        ]);

        return redirect("/release-notes/{$releaseNote->id}")->with('success', 'Release note updated');
    }
}
