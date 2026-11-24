<?php

namespace App\Http\Controllers;

use App\Actions\Tags\DeleteTag;
use App\Actions\Tags\UpdateTag;
use App\Models\Tag;
use App\Support\ClientContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $tags = Tag::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('title')
            ->withCount(['posts', 'contacts'])
            ->paginate();

        return view('tags.index', compact('tags'));
    }

    public function show(Request $request, $tagId)
    {
        $tag = Tag::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with(['contacts', 'posts'])
            ->findOrFail($tagId);

        return view('tags.show', compact('tag'));
    }

    public function create()
    {
        return view('tags.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => [
                'required',
                'string',
                Rule::unique('tags', 'title')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        $tag = Tag::create($request->merge([
            'client_id' => $request->attributes->get('fp_client_id'),
        ])->only('client_id', 'title'));

        return redirect('tags/'.$tag->id)->with('success', 'The tag has been created');
    }

    public function update(Request $request, UpdateTag $updateTag, $tagId)
    {
        $tag = $updateTag(ClientContext::fromDashboard($request), [
            ...$request->only('title'),
            'id' => $tagId,
        ]);

        return redirect('tags/'.$tag->id)->with('success', 'Tag renamed');
    }

    public function destroy(Request $request, DeleteTag $deleteTag, $tagId)
    {
        $deleteTag(ClientContext::fromDashboard($request), ['id' => $tagId]);

        return redirect('tags')->with('success', 'Tag deleted');
    }
}
