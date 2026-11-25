<?php

namespace App\Support;

/**
 * Derives the URL settings of an instance from APP_URL or APP_DOMAIN.
 *
 * The config files call these methods, so an instance with no variables
 * works on http://localhost:8080, and an instance with only APP_DOMAIN works
 * on https://APP_DOMAIN. A value that the operator sets always wins. An empty
 * value counts as not set, because a compose env_file with "APP_URL=" defines
 * the variable as an empty string.
 */
class InstanceUrl
{
    public const LOCAL_URL = 'http://localhost:8080';

    public static function url(?string $appUrl, ?string $appDomain): string
    {
        if (filled($appUrl)) {
            return rtrim($appUrl, '/');
        }

        return filled($appDomain) ? 'https://'.$appDomain : self::LOCAL_URL;
    }

    public static function host(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?: 'localhost';
    }

    public static function domain(?string $appDomain, string $url): string
    {
        return filled($appDomain) ? $appDomain : self::host($url);
    }

    /**
     * The leading dot shares the dashboard session with the project
     * subdomains. Browsers do not accept a cookie domain for localhost or
     * for an IP address, so those get no domain.
     */
    public static function sessionDomain(string $domain): ?string
    {
        $host = trim($domain, '[]');

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        return '.'.$domain;
    }

    public static function isSecure(string $url): bool
    {
        return str_starts_with(strtolower($url), 'https://');
    }

    /**
     * The hosts that get cookie-based Sanctum authentication: the local
     * defaults of Laravel, the app host, APP_DOMAIN and its subdomains. A
     * host gets the port of the URL when the URL has one.
     */
    public static function statefulDomains(string $url, string $domain): string
    {
        $port = parse_url($url, PHP_URL_PORT);
        $suffix = $port ? ':'.$port : '';

        return implode(',', array_unique([
            'localhost',
            'localhost:3000',
            '127.0.0.1',
            '127.0.0.1:8000',
            '::1',
            self::host($url).$suffix,
            $domain.$suffix,
            '*.'.$domain.$suffix,
        ]));
    }

    /**
     * The Docker image writes a new key to a file on the first boot. The
     * APP_KEY variable wins when it has a value.
     */
    public static function key(?string $appKey, ?string $file): ?string
    {
        if (filled($appKey)) {
            return $appKey;
        }

        if (blank($file) || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $key = trim((string) file_get_contents($file));

        return $key === '' ? null : $key;
    }
}
