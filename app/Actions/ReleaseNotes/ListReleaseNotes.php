<?php

namespace App\Actions\ReleaseNotes;

use App\Actions\Concerns\ResolvesListParams;
use App\Models\ReleaseNote;
use App\Support\ClientContext;
use Illuminate\Pagination\LengthAwarePaginator;

class ListReleaseNotes
{
    use ResolvesListParams;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): LengthAwarePaginator
    {
        $query = ReleaseNote::query()
            ->where('client_id', $context->clientId)
            ->with(['tags', 'votes', 'comments'])
            ->when(
                ! empty($input['search']),
                fn ($query) => $query->where(function ($q) use ($input) {
                    $search = $input['search'];
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                })
            )
            ->when(
                isset($input['published']) && $input['published'] !== '',
                fn ($query) => filter_var($input['published'], FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('published_at')
                    : $query->whereNull('published_at')
            )
            ->when(
                ! empty($input['tag']),
                fn ($query) => $query->whereHas('tags', fn ($q) => $q->where('title', $input['tag']))
            );

        $sortBy = $this->sortBy($input, ['created_at', 'updated_at', 'title', 'published_at'], 'created_at');
        if ($sortBy !== null) {
            $query->orderBy($sortBy, $this->sortDirection($input));
        }

        return $query->paginate($this->perPage($input), ['*'], 'page', $this->page($input));
    }
}
