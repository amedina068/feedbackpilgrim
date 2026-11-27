<?php

namespace App\Http\Controllers;

use App\Actions\Instance\CreateInstanceOwner;
use App\Support\InstanceSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The first-run page of a self-hosted instance. It creates the owner and the
 * first project.
 *
 * The page shows the form only for the correct setup token. Without the token,
 * it tells the operator where to find the setup URL. After the first user
 * exists, GET sends the visitor to the login page and POST returns 404.
 */
class SetupController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! InstanceSetup::isNeeded()) {
            return redirect('/login')->with('status', 'This instance is already set up. Log in with your account.');
        }

        $token = $this->tokenFrom($request->query('token'));

        return view('auth.setup', [
            'token' => $token,
            'tokenIsValid' => InstanceSetup::tokenMatches($token),
        ]);
    }

    public function store(Request $request, CreateInstanceOwner $owners): RedirectResponse
    {
        abort_unless(InstanceSetup::isNeeded(), 404);

        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        if (! InstanceSetup::tokenMatches($request->input('token'))) {
            throw ValidationException::withMessages([
                'token' => 'The setup token is not correct. Copy the setup URL from the log of the container.',
            ]);
        }

        $owner = $owners->createFirstOwner($request->only(['name', 'email', 'password', 'client_name', 'subdomain']));

        // Another request created the first account while this one waited.
        abort_if($owner === null, 404);

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    /**
     * Reads the token from the query. An operator can paste the full setup URL
     * into the token field, so the method also reads the token from a URL.
     */
    private function tokenFrom(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if (str_contains($value, 'token=')) {
            parse_str((string) parse_url($value, PHP_URL_QUERY), $query);
            $value = is_string($query['token'] ?? null) ? $query['token'] : '';
        }

        return $value;
    }
}
