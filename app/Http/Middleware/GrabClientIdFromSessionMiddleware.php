<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GrabClientIdFromSessionMiddleware
{
    /**
     * Put the current client id from the session into the request attributes.
     * Controllers read it with `$request->attributes->get('fp_client_id')`.
     * Request input never sets the current client. The session value counts
     * only while the user is still a member of that client. Otherwise the
     * middleware uses the first client of the user, or sets no client.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $clientId = session('fp_client_id');

        if (! $clientId || ! Auth::user()->clients()->whereKey($clientId)->exists()) {
            $client = Auth::user()->clients()->first();

            if (! $client) {
                session()->remove('fp_client_id');

                return $next($request);
            }

            $clientId = $client->id;
            session([
                'fp_client_id' => $clientId,
            ]);
        }

        $request->attributes->set('fp_client_id', (int) $clientId);

        return $next($request);
    }
}
