<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactImpersonationController extends Controller
{
    /**
     * Log the current admin into the client's public site as the given contact by
     * redirecting to that contact's signed authentication URL. The contact is scoped
     * to the admin's active client, so an admin can only impersonate their own
     * client's contacts.
     */
    public function show(Request $request, int $contactId): RedirectResponse
    {
        $contact = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($contactId);

        return redirect()->away($contact->generateAuthUrl());
    }
}
