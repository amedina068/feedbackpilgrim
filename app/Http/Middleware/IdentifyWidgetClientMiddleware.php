<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the client for the embed widget (pilgrim.js) from the value of the
 * script tag's data-client attribute. pilgrim.js sends that value as a bearer
 * header. The value is a public publishable key, not a credential, so this
 * middleware never touches Sanctum. Any other value gets 401.
 */
class IdentifyWidgetClientMiddleware
{
    private const PUBLISHABLE_KEY_PATTERN = '/^pk_[A-Za-z0-9]{32}$/';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $client = $this->resolveClient((string) $request->bearerToken());

        // A client that only deactivated users run gets the same answer as an
        // unknown key.
        if (! $client || $client->hasOnlyDeactivatedUsers()) {
            return response()->json([
                'message' => 'The publishable key is wrong. Copy the pk_ key from Settings > Embed Widget.',
                'error' => 'Invalid client key',
            ], 401);
        }

        $request->attributes->set('widgetClient', $client);

        return $next($request);
    }

    private function resolveClient(string $key): ?Client
    {
        if (preg_match(self::PUBLISHABLE_KEY_PATTERN, $key) !== 1) {
            return null;
        }

        return Client::query()->where('publishable_key', $key)->first();
    }
}
