<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $host = $request->getHost();

        if ($host === config('app.host') || $host === config('app.domain')) {
            return null;
        }

        if (! Str::endsWith($host, '.'.config('app.domain'))) {
            return null;
        }

        return $this->getClientFromSubdomain($host);
    }

    private function getClientFromSubdomain(string $host): ?Client
    {
        $subdomain = Str::beforeLast($host, '.'.config('app.domain'));

        if (in_array($subdomain, Client::RESERVED_SLUGS, true)) {
            return null;
        }

        return Client::where('slug', $subdomain)->first();
    }
}
