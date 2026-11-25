<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Domains OAuth clients may use for redirect URIs. Unused while the MCP
    | server authenticates with Sanctum bearer tokens (no OAuth in v1).
    |
    */

    'redirect_domains' => [
        '*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Instance-admin MCP server
    |--------------------------------------------------------------------------
    |
    | The admin server always runs over stdio: `php artisan mcp:start fp-admin`.
    | Set MCP_ADMIN_ENABLED=true to also serve it at /mcp/admin. That route
    | accepts only an owner token with the `instance:admin` ability that
    | expires in 7 days or less (`fp:token:create --admin`).
    |
    */

    'admin' => [
        'http_enabled' => (bool) env('MCP_ADMIN_ENABLED', false),
    ],

];
