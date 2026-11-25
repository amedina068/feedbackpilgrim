<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Support\ClientHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyClientMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $client = $this->getClient($request);

        // A client that only deactivated users run serves no public page.
        if (! $client || $client->hasOnlyDeactivatedUsers()) {
            return abort(404);
        }

        $request->merge([
            'client' => $client,
        ]);

        return $next($request);
    }

    private function getClient(Request $request): ?Client
    {
        return ClientHost::client($request->getHost());
    }
}
