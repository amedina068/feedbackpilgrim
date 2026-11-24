<?php

namespace App\Actions\Tags;

use App\Actions\Concerns\ResolvesListParams;
use App\Models\Tag;
use App\Support\ClientContext;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTags
{
    use ResolvesListParams;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): LengthAwarePaginator
    {
        $query = Tag::query()
            ->where('client_id', $context->clientId)
            ->withCount(['posts', 'contacts'])
            ->when(
                ! empty($input['search']),
                fn ($query) => $query->where('title', 'like', '%'.$input['search'].'%')
            );

        // Tag listing always orders; unknown sort columns fall back to title.
        $sortBy = $this->sortBy($input, ['title', 'created_at', 'updated_at'], 'title') ?? 'title';
        $query->orderBy($sortBy, $this->sortDirection($input, 'asc'));

        return $query->paginate($this->perPage($input), ['*'], 'page', $this->page($input));
    }
}
