<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Widget\InitRequest;
use App\Http\Traits\HandlesInitRequests;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class InitController extends Controller
{
    use HandlesInitRequests;

    /**
     * Without a user_hash, the public key alone can create a contact. One IP
     * address can create this number of such contacts for a client each hour.
     */
    public const NEW_UNVERIFIED_CONTACTS_PER_HOUR = 20;

    public function index(InitRequest $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('widgetClient');

        if (! $this->hasContactEmail($request)) {
            return response()->json([
                'message' => 'The request must include a contact with an email.',
                'error' => 'The request must include a contact with an email.',
            ], 422);
        }

        if (! $this->identityIsVerified($request, $client)) {
            return response()->json([
                'message' => 'Identity verification failed. Make userHash on your server with the identity secret of this project.',
                'error' => 'Identity verification failed',
            ], 403);
        }

        if ($this->createsUnverifiedContact($request, $client)) {
            $limitResponse = $this->limitUnverifiedContacts($request, $client);

            if ($limitResponse !== null) {
                return $limitResponse;
            }
        }

        $contact = $this->contactThatTheRequestCanUse($request, $client);
        $authUrl = $contact && ! $contact->user_id
            ? $contact->generateAuthUrl()
            : $client->public_site.'/posts';

        $releaseNotes = $this->handleReleaseNotes($request, $client, $contact);

        $testimonials = $this->handleTestimonials($request, $client, $contact);

        return response()->json([
            'auth_url' => $authUrl,
            'releaseNotes' => $releaseNotes,
            'testimonials' => $testimonials,
        ]);
    }

    /**
     * The publishable key is public, so it alone does not prove who the
     * contact is. Without a verified user_hash, the request can create a new
     * contact, but it cannot update or sign in as an existing contact. The
     * caller then gets null, a plain link to the public board, and release
     * notes that do not open by themselves, because the app cannot store the
     * last seen note. An admin contact (with a user_id) never gets a sign-in
     * link from the widget.
     */
    private function contactThatTheRequestCanUse(Request $request, Client $client): ?Contact
    {
        if (! $this->hasUserHash($request) && $this->findContact($request, $client)) {
            return null;
        }

        return $this->handleContact($request, $client);
    }

    private function createsUnverifiedContact(Request $request, Client $client): bool
    {
        return ! $this->hasUserHash($request) && ! $this->findContact($request, $client);
    }

    /**
     * A 429 response when this IP address made too many unverified contacts
     * for the client in the last hour. Otherwise null, and the attempt counts.
     */
    private function limitUnverifiedContacts(Request $request, Client $client): ?JsonResponse
    {
        $key = "widget-new-contact:{$client->id}:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($key, self::NEW_UNVERIFIED_CONTACTS_PER_HOUR)) {
            return response()->json(
                ['error' => 'Too many new contacts from this address. Try again later.'],
                429,
                ['Retry-After' => RateLimiter::availableIn($key)],
            );
        }

        RateLimiter::hit($key, 3600);

        return null;
    }

    /**
     * identityIsVerified() rejects a wrong hash, so a present hash is a verified hash here.
     */
    private function hasUserHash(Request $request): bool
    {
        $userHash = $request->input('user_hash');

        return $userHash !== null && $userHash !== '';
    }

    /**
     * The host app proves the contact's email with user_hash, an HMAC of the
     * email made with the client's identity secret. A wrong hash always
     * fails. A missing hash fails only when the client requires verification.
     */
    private function identityIsVerified(Request $request, Client $client): bool
    {
        $userHash = $request->input('user_hash');

        if ($userHash === null || $userHash === '') {
            return ! $client->require_identity_verification;
        }

        $email = $this->contactEmail($request);

        if (! is_string($userHash) || ! is_string($email) || blank($client->readableIdentitySecret())) {
            return false;
        }

        return hash_equals($client->identityHashFor($email), $userHash);
    }

    /**
     * The widget sends the contact as `contact` or as `contact.contact`.
     * A request without an email in either shape cannot identify a contact.
     */
    private function hasContactEmail(Request $request): bool
    {
        $email = $this->contactEmail($request);

        return is_string($email) && trim($email) !== '';
    }

    private function contactEmail(Request $request): mixed
    {
        $contact = $request->input('contact');

        if (! is_array($contact)) {
            return null;
        }

        $contactData = array_key_exists('contact', $contact) ? $contact['contact'] : $contact;

        return is_array($contactData) ? ($contactData['email'] ?? null) : null;
    }
}
