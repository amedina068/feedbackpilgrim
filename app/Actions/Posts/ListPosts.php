<?php

namespace App\Actions\Posts;

use App\Actions\Concerns\ResolvesListParams;
use App\Models\Post;
use App\Support\ClientContext;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPosts
{
    use ResolvesListParams;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): LengthAwarePaginator
    {
        $query = Post::query()
            ->where('client_id', $context->clientId)
            ->with(['contact', 'tags', 'votes'])
            ->when(
                ! empty($input['search']),
                fn ($query) => $query->where(function ($q) use ($input) {
                    $search = $input['search'];
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                })
            )
            ->when(
                ! empty($input['status']),
                fn ($query) => $query->where('status', $input['status'])
            )
            ->when(
                ! empty($input['board_id']),
                fn ($query) => $query->where('board_id', $input['board_id'])
            )
            ->when(
                ! empty($input['tag']),
                fn ($query) => $query->whereHas('tags', fn ($q) => $q->where('title', $input['tag']))
            )
            ->when(
                isset($input['published']) && $input['published'] !== '',
                fn ($query) => filter_var($input['published'], FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('published_at')
                    : $query->whereNull('published_at')
            );

        $sortBy = $this->sortBy($input, ['created_at', 'updated_at', 'title', 'status', 'published_at'], 'created_at');
        if ($sortBy !== null) {
            $query->orderBy($sortBy, $this->sortDirection($input));
        }

        return $query->paginate($this->perPage($input), ['*'], 'page', $this->page($input));
    }
}
