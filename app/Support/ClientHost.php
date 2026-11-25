<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Str;

/**
 * Finds the project (client) that a host serves. The public site of a
 * project is on the subdomain "<slug>.APP_DOMAIN". The app host, APP_DOMAIN
 * and the reserved slugs are never a project.
 */
class ClientHost
{
    public static function isAppHost(string $host): bool
    {
        $host = Str::lower($host);

        return $host === Str::lower((string) config('app.host'))
            || $host === Str::lower((string) config('app.domain'));
    }

    public static function client(string $host): ?Client
    {
        $host = Str::lower($host);
        $suffix = '.'.Str::lower((string) config('app.domain'));

        if (self::isAppHost($host) || ! Str::endsWith($host, $suffix)) {
            return null;
        }

        $subdomain = Str::beforeLast($host, $suffix);

        if ($subdomain === '' || in_array($subdomain, Client::RESERVED_SLUGS, true)) {
            return null;
        }

        return Client::where('slug', $subdomain)->first();
    }
}
