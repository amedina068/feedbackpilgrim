<?php

use App\Http\Middleware\EnsureInstanceAdminToken;
use App\Mcp\Servers\InstanceAdminServer;
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
| so the endpoint is CSRF-exempt. The token needs mcp:read or mcp:write. Each
| tool then checks its own ability.
|
*/

Mcp::web('/mcp', PublicServer::class)
    ->middleware(['auth:sanctum', 'api_client:mcp', 'throttle:mcp']);

/*
|--------------------------------------------------------------------------
| Instance-admin MCP server
|--------------------------------------------------------------------------
|
| The admin server runs over stdio. An operator with shell access starts it:
|
|   ssh <host> docker exec -i <container> php artisan mcp:start fp-admin
|
| With MCP_ADMIN_ENABLED=true, it also answers at /mcp/admin. That route
| accepts only an owner token with only the instance:admin ability that
| expires in 7 days or less. A product token never gets in.
|
*/

Mcp::local('fp-admin', InstanceAdminServer::class);

if (config('mcp.admin.http_enabled')) {
    Mcp::web('/mcp/admin', InstanceAdminServer::class)
        ->middleware(['auth:sanctum', EnsureInstanceAdminToken::class, 'throttle:mcp']);
}
