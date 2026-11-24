<?php

namespace App\Actions\Contacts;

use App\Actions\Concerns\ResolvesListParams;
use App\Models\Contact;
use App\Support\ClientContext;
use App\Support\ContactPresenter;
use Illuminate\Pagination\LengthAwarePaginator;

class ListContacts
{
    use ResolvesListParams;

    /**
     * Returns a paginator whose records are presented contact arrays (raw metadata
     * stripped, custom fields flattened) so both transports serialize identically.
     *
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): LengthAwarePaginator
    {
        $query = Contact::query()
            ->where('client_id', $context->clientId)
            ->with(['tags', 'votes'])
            ->when(
                ! empty($input['search']),
                fn ($query) => $query->where(function ($q) use ($input) {
                    $search = $input['search'];
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->when(
                ! empty($input['tag']),
                fn ($query) => $query->whereHas('tags', fn ($q) => $q->where('title', $input['tag']))
            );

        $sortBy = $this->sortBy($input, ['created_at', 'updated_at', 'name', 'email', 'mrr', 'ltv'], 'created_at');
        if ($sortBy !== null) {
            $query->orderBy($sortBy, $this->sortDirection($input));
        }

        $contacts = $query->paginate($this->perPage($input), ['*'], 'page', $this->page($input));
        $contacts->getCollection()->transform(fn ($contact) => ContactPresenter::present($contact));

        return $contacts;
    }
}
