<?php

namespace App\Mcp\Concerns;

use App\Mcp\Support\ToolResponse;
use App\Support\ClientContext;
use App\Support\NotFoundMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Shared tool plumbing: resolves the ClientContext from the authenticated Sanctum
 * token (client_id is never a tool parameter) and maps not-found to a safe error.
 */
trait ResolvesClientContext
{
    protected function context(Request $request): ClientContext
    {
        return ClientContext::fromToken($request->user()?->currentAccessToken());
    }

    /**
     * Run a tool body, translating a missing scoped record into a structured error
     * rather than leaking an exception or revealing another tenant's data.
     *
     * @param  callable(): Response  $callback
     */
    protected function guarded(callable $callback): Response
    {
        try {
            return $callback();
        } catch (ModelNotFoundException $exception) {
            return ToolResponse::error(NotFoundMessage::for($exception));
        }
    }
}
