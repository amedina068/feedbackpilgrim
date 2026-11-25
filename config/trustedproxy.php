<?php

/*
|--------------------------------------------------------------------------
| Cloudflare Edge IP Ranges
|--------------------------------------------------------------------------
|
| The default list of trusted proxies is the Cloudflare edge ranges. The
| TRUSTED_PROXIES setting replaces it. Refresh the list from
| https://www.cloudflare.com/ips/ when it changes.
|
*/

$cloudflare = [
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/13',
    '104.24.0.0/14',
    '172.64.0.0/13',
    '131.0.72.0/22',
    '2400:cb00::/32',
    '2606:4700::/32',
    '2803:f800::/32',
    '2405:b500::/32',
    '2405:8100::/32',
    '2a06:98c0::/29',
    '2c0f:f248::/32',
];

$fromEnv = trim((string) env('TRUSTED_PROXIES', ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The app reads the client IP from X-Forwarded-For only when the request
    | comes from one of these addresses. The rate limits and the logs use
    | that IP. Set TRUSTED_PROXIES to a comma-separated list of the IP
    | addresses or CIDR ranges of your own proxy. If TRUSTED_PROXIES is
    | empty, the app trusts the Cloudflare ranges. Do not use "*" when the
    | app port is open to the internet, because a client can then set its
    | own IP.
    |
    */

    // The loopback addresses are always trusted. Only a process on the same
    // host can use them, for example the Caddy server in the Docker image.
    'proxies' => array_values(array_unique([
        ...($fromEnv === ''
            ? $cloudflare
            : array_filter(array_map('trim', explode(',', $fromEnv)), fn (string $proxy): bool => $proxy !== '')),
        '127.0.0.1',
        '::1',
    ])),

];
