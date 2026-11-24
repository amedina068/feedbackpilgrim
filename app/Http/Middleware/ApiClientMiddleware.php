<?php

namespace App\Http\Middleware;

use App\Support\ClientContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ApiClientMiddleware
{
    /**
     * Resolve the authenticated token into a ClientContext and attach it to the
     * request. client_id is derived only from the token, never from request input.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $context = ClientContext::fromToken(Auth::user()?->currentAccessToken());
        } catch (UnauthorizedHttpException) {
            return response()->json([
                'message' => 'The API token belongs to no client. Make a new token in Settings > API Tokens.',
                'error' => 'Invalid API token',
            ], 401);
        }

        $request->attributes->set('clientContext', $context);

        return $next($request);
    }
}
