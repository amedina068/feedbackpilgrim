<?php

namespace App\Http\Middleware;

use App\Support\ApiTokens;
use App\Support\ClientContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ApiClientMiddleware
{
    /**
     * Resolve the authenticated token into a ClientContext and attach it to the
     * request. client_id is derived only from the token, never from request input.
     *
     * The surface is `api` (the REST API, the default) or `mcp` (the MCP
     * server). The token must have an ability for that surface.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $surface = 'api'): Response
    {
        $abilities = $surface === 'mcp'
            ? [ApiTokens::MCP_READ, ApiTokens::MCP_WRITE]
            : [ApiTokens::API];

        try {
            $context = ClientContext::fromToken(Auth::user()?->currentAccessToken(), ...$abilities);
        } catch (UnauthorizedHttpException) {
            return response()->json([
                'message' => 'The API token belongs to no client. Make a new token in Settings > API Tokens.',
                'error' => 'Invalid API token',
            ], 401);
        } catch (AccessDeniedHttpException) {
            return response()->json([
                'message' => 'This API token cannot use this endpoint. It needs one of these abilities: '.implode(', ', $abilities).'. Make a new token in Settings > API Tokens.',
                'error' => 'insufficient_ability',
            ], 403);
        }

        $request->attributes->set('clientContext', $context);

        return $next($request);
    }
}
