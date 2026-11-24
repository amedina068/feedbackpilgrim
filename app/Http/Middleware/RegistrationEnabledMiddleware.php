<?php

namespace App\Http\Middleware;

use App\Support\Registration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationEnabledMiddleware
{
    /**
     * Return 404 for the registration routes when registration is closed.
     * The owner sets the mode in the admin area (`App\Support\Registration`).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Registration::isOpen()) {
            abort(404);
        }

        return $next($request);
    }
}
