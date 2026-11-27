<?php

namespace App\Mcp\Concerns;

use App\Mcp\Support\ToolResponse;
use App\Support\ApiTokens;
use App\Support\ClientContext;
use App\Support\NotFoundMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Shared tool plumbing: resolves the ClientContext from the authenticated Sanctum
 * token (client_id is never a tool parameter) and maps not-found to a safe error.
 */
trait ResolvesClientContext
{
    protected function context(Request $request): ClientContext
    {
        return ClientContext::fromToken($request->user()?->currentAccessToken(), $this->requiredAbility());
    }

    /**
     * A tool with `readOnlyHint` needs `mcp:read`. Every other tool needs
     * `mcp:write`.
     */
    public function requiredAbility(): string
    {
        return ($this->annotations()['readOnlyHint'] ?? false) === true
            ? ApiTokens::MCP_READ
            : ApiTokens::MCP_WRITE;
    }

    /**
     * laravel/mcp lists and calls only the tools that register. A token
     * without the ability does not see the tool, and a call to it returns
     * "tool not found". Without a token, the route middleware already refused
     * the request.
     */
    public function shouldRegister(): bool
    {
        $token = Auth::user()?->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can($this->requiredAbility());
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
