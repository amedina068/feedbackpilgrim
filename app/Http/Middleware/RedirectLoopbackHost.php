<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin routes bind to the host of APP_URL. A person who opens
 * http://127.0.0.1:8080 or http://[::1]:8080 gets the same page on the
 * APP_URL host with the same port, not a 404.
 *
 * The health check and the Caddy "ask" route answer on any host, so this
 * middleware does not touch them.
 */
class RedirectLoopbackHost
{
    private const LOOPBACK_HOSTS = ['127.0.0.1', '::1', '[::1]'];

    public function handle(Request $request, Closure $next): Response
    {
        $appHost = (string) config('app.host');
        $host = $request->getHost();

        if (! in_array($host, self::LOOPBACK_HOSTS, true)
            || in_array($appHost, self::LOOPBACK_HOSTS, true)
            || ! $request->isMethodSafe()
            || $request->is('up', 'caddy/ask')) {
            return $next($request);
        }

        $port = $request->getPort();
        $isDefaultPort = ($request->isSecure() && $port === 443) || (! $request->isSecure() && $port === 80);

        return redirect()->to(
            $request->getScheme().'://'.$appHost.($isDefaultPort ? '' : ':'.$port).$request->getRequestUri()
        );
    }
}
