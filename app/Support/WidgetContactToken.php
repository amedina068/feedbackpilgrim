<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Contact;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * A signed value that names the contact of a widget page view. `init` puts
 * it in the testimonial iframe URL, and the widget sends it back with the
 * testimonial. The value is encrypted and signed with the app key, so a
 * guest cannot make one for another contact.
 */
final class WidgetContactToken
{
    public const LIFETIME_HOURS = 24;

    public static function issue(Contact $contact, ?CarbonInterface $expiresAt = null): string
    {
        $expiresAt ??= now()->addHours(self::LIFETIME_HOURS);

        return Crypt::encryptString(json_encode([
            'contact_id' => $contact->id,
            'client_id' => $contact->client_id,
            'expires_at' => $expiresAt->getTimestamp(),
        ]));
    }

    /**
     * The contact of a valid token for this client, or null.
     */
    public static function resolve(mixed $token, Client $client): ?Contact
    {
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)
            || (int) ($payload['client_id'] ?? 0) !== (int) $client->id
            || (int) ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return Contact::query()
            ->where('client_id', $client->id)
            ->find((int) ($payload['contact_id'] ?? 0));
    }
}
