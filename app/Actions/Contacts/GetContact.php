<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Support\ClientContext;
use App\Support\ContactPresenter;

class GetContact
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function __invoke(ClientContext $context, array $input): array
    {
        $contact = Contact::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id'])
            ->load(['tags', 'votes']);

        return ContactPresenter::present($contact);
    }
}
