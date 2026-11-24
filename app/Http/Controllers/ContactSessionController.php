<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactSessionController extends Controller
{
    /**
     * Sign in a contact from a link that Contact::generateAuthUrl() made. The
     * link must have a valid signature and an expiry. The contact must belong
     * to the client of this host. The redirect stays on this host.
     */
    public function store(Request $request, string $contactUuid): RedirectResponse
    {
        if (! $request->query('expires') || ! $request->hasValidRelativeSignature()) {
            return redirect('/posts')->with('info', "This sign-in link is not valid or has expired. Open the board again from your {$request->client->name} account.");
        }

        $contact = Contact::query()
            ->where('uuid', $contactUuid)
            ->where('client_id', $request->client->id)
            ->firstOrFail();

        // The admin contact acts as its user. A deactivated user cannot sign in
        // through this link.
        abort_if($contact->actsForDeactivatedUser(), 404);

        Auth::guard('contact')->login($contact, true);

        return redirect($this->relativeRedirect($request->query('redirect_url')));
    }

    /**
     * Only a path on this host. A URL with a scheme or a host goes to /posts.
     */
    private function relativeRedirect(mixed $redirect): string
    {
        if (! is_string($redirect)
            || ! str_starts_with($redirect, '/')
            || str_starts_with($redirect, '//')
            || str_contains($redirect, '\\')) {
            return '/posts';
        }

        return $redirect;
    }
}
