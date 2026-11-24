<?php

use App\Mcp\Servers\PublicServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP (AI) Routes
|--------------------------------------------------------------------------
|
| The public MCP server is a thin transport over the same Actions the REST API
| uses. It is stateless and bearer-only: the api_client middleware resolves the
| ClientContext from the Sanctum token (rejecting session/cookie auth, which
| lacks a client_id), and the mcp limiter throttles per token. No 'web' group,
| so the endpoint is CSRF-exempt.
|
*/

Mcp::web('/mcp', PublicServer::class)
    ->middleware(['auth:sanctum', 'api_client', 'throttle:mcp']);
