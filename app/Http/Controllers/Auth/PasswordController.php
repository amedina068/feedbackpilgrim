<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // After a one-time login link, the user has no known password yet.
        $fromLoginLink = (bool) $request->session()->get(LoginLink::SESSION_KEY);

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $fromLoginLink ? ['nullable'] : ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $request->session()->forget(LoginLink::SESSION_KEY);

        return back()->with('status', 'password-updated');
    }
}
