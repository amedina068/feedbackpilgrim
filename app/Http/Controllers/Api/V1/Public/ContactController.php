<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\GetContact;
use App\Actions\Contacts\ListContacts;
use App\Actions\Contacts\UpdateContact;
use App\Support\ContactPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends PublicApiController
{
    public function index(Request $request): JsonResponse
    {
        $contacts = (new ListContacts)($this->clientContext($request), $request->all());

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $result = (new CreateContact)($this->clientContext($request), $request->all());

        return response()->json(
            ContactPresenter::present($result->contact),
            $result->wasCreated ? 201 : 200,
        );
    }

    public function show(Request $request, $contactId): JsonResponse
    {
        $contact = (new GetContact)($this->clientContext($request), ['id' => $contactId]);

        return response()->json($contact);
    }

    public function update(Request $request, $contactId): JsonResponse
    {
        $contact = (new UpdateContact)($this->clientContext($request), [...$request->all(), 'id' => $contactId]);

        return response()->json($contact);
    }
}
