<?php

namespace App\Actions\Testimonials;

use App\Actions\Concerns\ResolvesListParams;
use App\Models\Testimonial;
use App\Support\ClientContext;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTestimonials
{
    use ResolvesListParams;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): LengthAwarePaginator
    {
        $query = Testimonial::query()
            ->where('client_id', $context->clientId)
            ->with(['contact', 'votes', 'comments'])
            ->when(
                ! empty($input['search']),
                fn ($query) => $query->where(function ($q) use ($input) {
                    $search = $input['search'];
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                })
            )
            ->when(
                filter_var($input['approved'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query->approved(),
                fn ($query) => $query->pending(),
            )
            ->when(
                ! empty($input['rating']),
                fn ($query) => $query->where('rating', $input['rating'])
            );

        $sortBy = $this->sortBy($input, ['created_at', 'updated_at', 'rating', 'approved_at'], 'created_at');
        if ($sortBy !== null) {
            $query->orderBy($sortBy, $this->sortDirection($input));
        }

        return $query->paginate($this->perPage($input), ['*'], 'page', $this->page($input));
    }
}
