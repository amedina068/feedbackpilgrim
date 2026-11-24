<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Rules\Public\ContactRules;
use App\Support\ClientContext;
use App\Support\ContactPresenter;
use App\Support\ContactUpsertResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateContact
{
    /**
     * Upsert a contact by (client, email). Custom fields outside the known columns
     * are stored as metadata. Returns the contact plus whether it was newly created.
     *
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): ContactUpsertResult
    {
        $data = Validator::make($input, ContactRules::store($context->clientId))->validate();

        $metadata = ContactPresenter::extractMetadata($input);
        ContactPresenter::guardMetadata($metadata);

        return DB::transaction(function () use ($context, $data, $input, $metadata) {
            $contact = Contact::query()
                ->where('client_id', $context->clientId)
                ->where('email', $data['email'])
                ->first();

            $base = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            foreach (['mrr', 'ltv', 'avatar'] as $field) {
                if (array_key_exists($field, $input)) {
                    $base[$field] = $input[$field];
                }
            }

            if ($contact) {
                if (! empty($metadata)) {
                    $base['metadata'] = ContactPresenter::encodeMetadata($metadata, $contact);
                }

                $contact->update($base);
                $wasCreated = false;
            } else {
                $contact = Contact::create(array_merge([
                    'mrr' => $input['mrr'] ?? 0,
                    'ltv' => $input['ltv'] ?? 0,
                    'avatar' => $input['avatar'] ?? '',
                    'client_id' => $context->clientId,
                    'metadata' => ContactPresenter::encodeMetadata($metadata),
                ], $base));
                $wasCreated = true;
            }

            if (array_key_exists('tags', $data)) {
                $contact->tags()->sync($data['tags']);
            }

            return new ContactUpsertResult($contact->load(['tags']), $wasCreated);
        });
    }
}
