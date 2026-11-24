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

];
