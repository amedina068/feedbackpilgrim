<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::query()
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('title', 'LIKE', "%{$request->search}%")
                    ->orWhere('content', 'LIKE', "%{$request->search}%")
                )
            )
            ->when(
                $this->sort($request),
                fn ($query, array $sort) => $query->orderBy(...$sort),
                fn ($query) => $query->latest()
            )
            ->when(
                ! $request->filled('completed'),
                fn ($query) => $query->where('status', '!=', Post::STATUS_COMPLETED)
            )
            ->when(
                $request->filled('archived'),
                fn ($query) => $query->where(fn ($query) => $query->whereNull('archived_at')->orWhereNotNull('archived_at')),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->when(
                $request->filled('tags'),
                fn ($query) => $query->join(
                    'taggables',
                    fn ($query) => $query->on('posts.id', 'taggables.taggable_id')
                        ->where('taggables.taggable_type', Post::class)
                )
                    ->whereIn('taggables.tag_id', $request->tags)
            )
            ->where('posts.client_id', $request->attributes->get('fp_client_id'))
            ->forBoard($request->board)
            ->with(['tags', 'contact:id,name,top_customer'])
            ->withCount(['votes', 'comments'])
            ->withScore()
            ->latest()
            ->paginate(12)
            ->withQueryString();

        foreach ($posts->items() as $item) {
            $item->append(['total_votes']);
        }

        $tags = Tag::query()->where('client_id', $request->attributes->get('fp_client_id'))->orderBy('title')->get();
        $boards = Board::query()->where('client_id', $request->attributes->get('fp_client_id'))->orderBy('position')->get();

        return view('posts.index', compact('posts', 'tags', 'boards'));
    }

    public function show(Request $request, $postId)
    {
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'contact',
                'tags',
                'media',
                'comments' => fn ($query) => $query->latest()->take(5)->with(['contact', 'media']),
                'votes' => fn ($query) => $query->latest()->take(5)->with('contact'),
            ])
            ->withCount(['votes', 'comments'])
            ->groupBy('posts.id')
            ->findOrFail($postId);

        $stages = Stage::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('position')
            ->get();

        $otherPosts = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->whereKeyNot($post->id)
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('posts.show', compact('post', 'stages', 'otherPosts'));
    }

    /**
     * The sorts of the posts page: the `sort_by` value, the column and the direction.
     */
    private const SORTS = [
        'created_at__DESC' => ['posts.created_at', 'desc'],
        'created_at__ASC' => ['posts.created_at', 'asc'],
        'votes_count__DESC' => ['votes_count', 'desc'],
        'votes_count__ASC' => ['votes_count', 'asc'],
        'score__DESC' => ['score', 'desc'],
        'score__ASC' => ['score', 'asc'],
    ];

    /**
     * The column and the direction of a known `sort_by` value, or null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function sort(Request $request): ?array
    {
        $sortBy = $request->query('sort_by');

        return is_string($sortBy) ? (self::SORTS[$sortBy] ?? null) : null;
    }

    public function create(Request $request)
    {
        $contacts = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->whereNull('user_id')
            ->orderBy('email')
            ->get();

        $tags = Tag::query()->where('client_id', $request->attributes->get('fp_client_id'))->orderBy('title')->get();

        return view('posts.create', compact('contacts', 'tags'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'contact_id' => [
                'integer',
                'nullable',
                Rule::exists('contacts', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
            'title' => ['required', 'string'],
            'content' => ['string', 'nullable'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        $clientId = $request->attributes->get('fp_client_id');

        $contact = $request->filled('contact_id')
            ? Contact::query()->where('client_id', $clientId)->findOrFail($request->contact_id)
            : Auth::user()->adminContactFor($clientId);

        $post = Post::create(array_merge(
            $request->only(['title', 'content', 'contact_id']),
            [
                'client_id' => $request->attributes->get('fp_client_id'),
                'status' => Post::STATUS_PENDING,
                'contact_id' => $contact->id,
            ],
        ));

        if ($request->filled('tags')) {
            $post->tags()->sync($request->tags);
        }

        return redirect('/posts/'.$post->id)->with('success', 'Post created successfully');
    }

    public function edit(Request $request, $postId)
    {
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with(['tags'])
            ->findOrFail($postId);

        $contacts = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('email')
            ->get();

        $tags = Tag::query()->where('client_id', $request->attributes->get('fp_client_id'))->orderBy('title')->get();

        return view('posts.edit', compact('contacts', 'tags', 'post'));
    }

    public function update(Request $request, $postId)
    {
        $request->validate([
            'contact_id' => [
                'integer',
                'nullable',
                Rule::exists('contacts', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
            'title' => ['required', 'string'],
            'content' => ['string', 'nullable'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);
        $post = Post::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($postId);

        $post->update($request->only(['title', 'content', 'contact_id']));

        $post->tags()->sync($request->tags);

        return redirect('/posts/'.$post->id)->with('success', 'Post updated successfully');
    }
}
