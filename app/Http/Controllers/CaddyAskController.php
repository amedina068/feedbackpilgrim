<?php

namespace App\Http\Controllers;

use App\Support\ClientHost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The "ask" endpoint of Caddy on-demand TLS. Caddy calls it before it gets a
 * certificate for a new host. A 200 answer allows the certificate. Any other
 * answer refuses it, so a stranger cannot make Caddy get certificates for
 * random hosts that point at the server.
 */
class CaddyAskController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $host = strtolower(trim((string) $request->query('domain', '')));

        if ($host === '') {
            return response('', 404);
        }

        if (ClientHost::isAppHost($host)) {
            return response('', 200);
        }

        $client = ClientHost::client($host);

        if ($client === null || $client->hasOnlyDeactivatedUsers()) {
            return response('', 404);
        }

        return response('', 200);
    }
}
