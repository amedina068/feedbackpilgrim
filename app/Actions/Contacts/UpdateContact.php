<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Rules\Public\ContactRules;
use App\Support\ClientContext;
use App\Support\ContactPresenter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdateContact
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function __invoke(ClientContext $context, array $input): array
    {
        $contact = Contact::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id']);

        $data = Validator::make($input, ContactRules::update($context->clientId, $contact->id))->validate();

        return DB::transaction(function () use ($contact, $data, $input) {
            // mrr/ltv have no validation rule but are accepted columns; pull the
            // persisted values from raw input (validation already ran on it).
            $contact->update(Arr::only($input, ['name', 'email', 'mrr', 'ltv', 'avatar']));

            if (array_key_exists('tags', $data)) {
                $contact->tags()->sync($data['tags']);
            }

            return ContactPresenter::present($contact->load(['tags']));
        });
    }
}
