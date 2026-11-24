<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminContactPublicSiteLoginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $client = $request->client;

        if ($client->id != $request->attributes->get('fp_client_id')) {
            return $next($request);
        }

        $contact = Auth::user()->adminContactFor($client);

        Auth::guard('contact')->login($contact, true);

        return $next($request);
    }
}
