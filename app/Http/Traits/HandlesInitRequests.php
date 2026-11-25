<?php

namespace App\Http\Traits;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Support\WidgetContactToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

trait HandlesInitRequests
{
    protected function handleContact(Request $request, Client $client): Contact
    {
        $contactData = $this->contactData($request);

        $contact = $this->findContact($request, $client);

        if (! $contact) {
            $contact = Contact::create([
                'email' => $this->normalizedEmail($contactData['email']),
                'client_id' => $client->id,
                'name' => $contactData['name'] ?? '',
                'mrr' => $contactData['mrr'] ?? 0,
                'ltv' => $contactData['ltv'] ?? 0,
                'avatar' => $contactData['avatar'] ?? '',
                'metadata' => json_encode(Arr::except($contactData, ['name', 'email', 'top_customer', 'mrr', 'ltv'])),
            ]);
        } else {
            $contact->update([
                'name' => $contactData['name'] ?? $contact->name,
                'mrr' => $contactData['mrr'] ?? $contact->mrr,
                'ltv' => $contactData['ltv'] ?? $contact->ltv,
                'avatar' => $contactData['avatar'] ?? $contact->avatar,
                'metadata' => json_encode(array_merge(
                    Arr::except($contactData, ['name', 'email', 'top_customer', 'mrr', 'ltv']),
                    $contact->pilgrimFields ?? []
                )),
            ]);
        }

        $timestamp = $this->logLogin($contact);

        $contact->update([
            'metadata' => json_encode(array_merge(
                json_decode($contact->metadata, true) ?? [],
                $contact->pilgrimFields ?? [],
                ['pilgrim_last_login' => $timestamp]
            )),
        ]);

        return $contact;
    }

    /**
     * The contact of the client with the email of the request, if it exists.
     *
     * The user_hash proves the trimmed, lowercase email. The MySQL collation
     * ignores case and accents, so the query can return a contact with a
     * different email. Thus a binary comparison of the normalized emails
     * selects the contact.
     */
    protected function findContact(Request $request, Client $client): ?Contact
    {
        $requestEmail = trim((string) $this->contactData($request)['email']);
        $email = $this->normalizedEmail($requestEmail);

        return Contact::query()
            ->where('client_id', $client->id)
            ->whereIn('email', array_unique([$email, $requestEmail]))
            ->orderBy('id')
            ->get()
            ->first(fn (Contact $contact): bool => $this->normalizedEmail($contact->email) === $email);
    }

    protected function normalizedEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * @return array<string, mixed>
     */
    protected function contactData(Request $request): array
    {
        return array_key_exists('contact', $request->contact)
            ? $request->contact['contact']
            : $request->contact;
    }

    protected function handleReleaseNotes(Request $request, Client $client, ?Contact $contact = null): array
    {
        $releaseNotes = [];

        if (! $request->releaseNotes) {
            return $releaseNotes;
        }

        if (Arr::get($request->releaseNotes, 'show')) {
            $releaseNotes['url'] = $client->releaseNotesIframeUrl;
            $releaseNotes['show'] = true;
        }

        $releaseNotes['open'] = $contact !== null
            && $this->shouldOpenModalByDefault($contact, (bool) Arr::get($request->releaseNotes, 'open', false));

        return $releaseNotes;
    }

    protected function handleTestimonials(Request $request, Client $client, ?Contact $contact = null): array
    {
        $testimonials = [];

        if (! $request->testimonials) {
            return $testimonials;
        }

        if ($contact) {
            $testimonials['url'] = $client->testimonialsIframeUrl.'?contact_token='.urlencode(WidgetContactToken::issue($contact));
        } else {
            $testimonials['url'] = $client->testimonialsIframeUrl;
        }

        return $testimonials;
    }

    protected function logLogin(Contact $contact): string
    {
        $timestamp = Cache::get('login_'.$contact->id);

        if (! $timestamp) {
            return $this->createTimestamp($contact);
        }

        if ((new Carbon($timestamp))->lessThan(now()->startOfDay())) {
            $timestamp = $this->createTimestamp($contact);
        }

        return $timestamp;
    }

    protected function createTimestamp(Contact $contact): string
    {
        Activity::create([
            'client_id' => $contact->client_id,
            'log_name' => 'default',
            'description' => 'login',
            'causer_id' => $contact->id,
            'causer_type' => get_class($contact),
        ]);

        $timestamp = now()->format('Y-m-d H:i:s');

        Cache::put('login_'.$contact->id, $timestamp, now()->addDay());

        return $timestamp;
    }

    protected function shouldOpenModalByDefault(Contact $contact, bool $releaseNotesOpen): bool
    {
        if (! $releaseNotesOpen) {
            return false;
        }

        $lastReleaseNote = $contact->getPilgrimField('pilgrim_last_release_note');

        $releaseNote = ReleaseNote::query()
            ->where('client_id', $contact->client_id)
            ->published()
            ->latest('published_at')
            ->first();

        if (! $releaseNote) {
            return false;
        }

        $shouldOpenModal = $releaseNote->id != $lastReleaseNote;

        if (! $shouldOpenModal) {
            return false;
        }

        $contact->setPilgrimField('pilgrim_last_release_note', $releaseNote->id);

        return true;
    }
}
