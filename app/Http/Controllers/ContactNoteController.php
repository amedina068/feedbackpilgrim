<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactNoteController extends Controller
{
    public function edit(Request $request, $contactId): View
    {
        $contact = $this->contact($request, $contactId);

        return view('contact-notes.edit', compact('contact'));
    }

    public function update(Request $request, $contactId): RedirectResponse
    {
        $request->validate([
            'notes' => ['string', 'nullable'],
        ]);

        $contact = $this->contact($request, $contactId);

        $contact->update($request->only('notes'));

        return redirect("contacts/{$contact->id}")->with('success', 'Notes updated successfully');
    }

    /**
     * The contact of the URL, in the current client only.
     */
    private function contact(Request $request, mixed $contactId): Contact
    {
        return Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($contactId);
    }
}
