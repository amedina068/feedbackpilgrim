<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LoginLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs a user in with a one-time link from `fp:install` or from the
 * `invite-user` admin tool. The user then sets a password on the profile page.
 */
class LoginLinkController extends Controller
{
    public const INVALID_MESSAGE = 'This login link does not work. It expired, or somebody used it. Ask the owner of this instance for a new link.';

    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()
            || ! LoginLink::isCurrent($user, $request->query('version'))
            || $user->isDeactivated()) {
            return redirect()->route('login')->with('status', self::INVALID_MESSAGE);
        }

        LoginLink::consume($user);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(LoginLink::SESSION_KEY, true);

        return redirect()->route('profile.edit');
    }
}
