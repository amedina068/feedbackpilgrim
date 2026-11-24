<?php

namespace App\Support;

class WebhookUrlGuard
{
    /**
     * Address ranges that FILTER_FLAG_GLOBAL_RANGE accepts, but that must not
     * receive a webhook: IPv4 multicast, the NAT64 prefixes (they can map to
     * an internal IPv4 address) and IPv6 multicast.
     *
     * @var list<string>
     */
    private const EXTRA_REFUSED_RANGES = [
        '224.0.0.0/4',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        'ff00::/8',
    ];

    public function __construct(private HostResolver $resolver) {}

    /**
     * Return the address to connect to for a webhook URL. Return null when the
     * URL is not https, has credentials, or when its host does not resolve
     * only to public addresses.
     */
    public function publicAddressFor(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $host = trim($parts['host'], '[]');

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->resolver->resolve($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    /**
     * Return the CURLOPT_RESOLVE entry that pins the host of the URL to the
     * address. Return null when the host is an IP address, because then no
     * DNS lookup occurs.
     */
    public static function curlResolveEntry(string $url, string $address): ?string
    {
        $parts = parse_url($url);
        $host = trim($parts['host'] ?? '', '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $port = $parts['port'] ?? 443;
        $target = str_contains($address, ':') ? '['.$address.']' : $address;

        return $host.':'.$port.':'.$target;
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        foreach (self::EXTRA_REFUSED_RANGES as $range) {
            if (self::inRange($address, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function inRange(string $address, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);

        $addressBytes = inet_pton($address);
        $subnetBytes = inet_pton($subnet);

        if ($addressBytes === false || $subnetBytes === false || strlen($addressBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $bits = (int) $bits;
        $fullBytes = intdiv($bits, 8);

        if (substr($addressBytes, 0, $fullBytes) !== substr($subnetBytes, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressBytes[$fullBytes]) & $mask) === (ord($subnetBytes[$fullBytes]) & $mask);
    }
}
