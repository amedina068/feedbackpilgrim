<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Client;
use App\Models\Post;
use App\Models\Tag;
use App\Notifications\PostCreatedByContact;
use App\Support\UploadFileName;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientPostController extends Controller
{
    use LogsActivity;

    /**
     * The sorts of the public posts page: the `sort_by` value, the label, the column and the direction.
     */
    private const SORTS = [
        'created_at__DESC' => ['Newest', 'posts.created_at', 'desc'],
        'created_at__ASC' => ['Oldest', 'posts.created_at', 'asc'],
        'votes_count__DESC' => ['Most Voted', 'votes_count', 'desc'],
        'votes_count__ASC' => ['Least Voted', 'votes_count', 'asc'],
    ];

    public function index(Request $request)
    {
        $client = $request->client;

        $boards = Board::query()->where('client_id', $client->id)->public()->orderBy('position')->get();

        $boardId = $request->filled('board')
            ? $boards->firstWhere('slug', $request->board)?->id
            : null;

        $sortBy = $this->sortBy($request);
        [$showingPostsFilter, $column, $direction] = self::SORTS[$sortBy];

        $posts = Post::query()
            ->leftJoin('votes', function ($join) {
                $join->on('posts.id', '=', 'votes.votable_id')
                    ->where('votes.votable_type', '=', Post::class);
            })
            ->where('client_id', $client->id)
            ->forBoard($boardId)
            ->when(
                ! $request->filled('completed'),
                fn ($query) => $query->where('status', '!=', Post::STATUS_COMPLETED)
            )
            ->orderBy($column, $direction)
            ->public()
            ->with(['contact'])
            ->select(['posts.*', DB::raw('COUNT(votes.id) as votes_count')])
            ->votedByContact(Auth::guard('contact')->id())
            ->groupBy('posts.id')
            ->with(['comments' => fn ($query) => $query->latest()->with(['contact'])])
            ->paginate();

        return view('client-posts.index', compact('client', 'posts', 'showingPostsFilter', 'sortBy', 'boards'));
    }

    public function show(Request $request, $postId)
    {
        $client = $request->client;

        $post = Post::query()
            ->where('client_id', $client->id)
            ->where('posts.id', $postId)
            ->public()
            ->with([
                'contact',
                'media',
                'comments' => fn ($query) => $query->latest()->with(['contact', 'media']),
            ])
            ->leftJoin('votes', function ($join) {
                $join->on('posts.id', '=', 'votes.votable_id')
                    ->where('votes.votable_type', '=', Post::class);
            })
            ->select(['posts.*', DB::raw('COUNT(votes.id) as votes_count')])
            ->with(['votes' => fn ($query) => $query->take(5)])
            ->groupBy('posts.id')
            ->votedByContact(Auth::guard('contact')->id())
            ->latest()
            ->firstOrFail()
            ->append(['total_votes']);

        return view('client-posts.show', compact('client', 'post'));
    }

    public function create(Request $request)
    {
        $client = $request->client;

        $tags = Tag::where('client_id', $client->id)->orderBy('title')->get();

        return view('client-posts.create', compact('client', 'tags'));
    }

    public function store(Request $request)
    {
        $client = $request->client;

        $contact = Auth::guard('contact')->user();

        if ($client->id != $contact->client_id) {
            return abort(401);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $client->id),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt',
                'max:10240',
            ],
        ]);

        $post = Post::create($request->merge([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'published_at' => now(),
            'status' => Post::STATUS_PENDING,
        ])->only(['status', 'client_id', 'contact_id', 'published_at', 'content', 'title']));

        $this->logActivity($contact->client_id, $contact, $post, 'post_created');

        foreach ($request->file('attachments', []) as $attachment) {
            $post->addMedia($attachment)
                ->usingFileName(UploadFileName::for($attachment))
                ->toMediaCollection('attachments');
        }

        if (count($request->get('tags', []))) {
            $post->tags()->sync($request->tags);
        }

        $post->votes()->create(['contact_id' => $contact->id]);

        $this->logActivity($contact->client_id, $contact, $post, 'vote_created');

        $this->notifyUser($post);

        // A post on a private board is for the team only. Its public page returns 404.
        $target = $post->board()->value('is_public') ? "/posts/{$post->id}" : '/posts';

        return redirect($target)->with('success', 'Post created successfully');
    }

    private function notifyUser($post)
    {
        $client = Client::find($post->client_id);

        $user = $client->users()->first();

        if ($user->id == $post->contact->user_id) {
            return;
        }

        $user->notify(new PostCreatedByContact($post));
    }

    /**
     * An unknown `sort_by` value shows the newest posts.
     */
    private function sortBy(Request $request): string
    {
        $sortBy = $request->query('sort_by');

        return is_string($sortBy) && array_key_exists($sortBy, self::SORTS) ? $sortBy : 'created_at__DESC';
    }
}
