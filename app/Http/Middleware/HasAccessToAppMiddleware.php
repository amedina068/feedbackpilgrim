<?php

namespace App\Http\Middleware;

use App\Contracts\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HasAccessToAppMiddleware
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * Send a user who has not finished onboarding to the next onboarding
     * step. Then send the user to the URL that Entitlements::accessRedirect()
     * gives. The owner of the instance always gets in.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user->hasAccess()) {
            return redirect($user->onboardingStep());
        }

        if (! $user->isOwner()) {
            $url = $this->entitlements->accessRedirect($user);

            if ($url !== null) {
                return redirect($url);
            }
        }

        return $next($request);
    }
}
