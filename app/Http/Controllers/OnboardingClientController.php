<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClientForOwner;
use App\Contracts\Entitlements;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnboardingClientController extends Controller
{
    public function create(Entitlements $entitlements): View
    {
        $user = Auth::user();

        if (! $this->canCreateProject($user, $entitlements)) {
            return view('onboarding.project-limit', [
                'message' => $entitlements->projectLimitMessage($user),
            ]);
        }

        return view('onboarding.client');
    }

    public function store(Request $request, CreateClientForOwner $createClient, Entitlements $entitlements): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($this->canCreateProject($user, $entitlements), 403, $entitlements->projectLimitMessage($user));

        $request->validate([
            'name' => ['required', 'string'],
            'subdomain' => CreateClientForOwner::subdomainRules(),
        ], [
            'subdomain.not_in' => 'This subdomain is reserved. Choose a different subdomain.',
        ]);

        $client = $createClient($user, (string) $request->input('name'), (string) $request->input('subdomain'));

        session(['fp_client_id' => $client->id]);

        return redirect('dashboard');
    }

    /** The owner of the instance has no project limit. Entitlements decides for all other users. */
    private function canCreateProject(User $user, Entitlements $entitlements): bool
    {
        return $user->isOwner() || $entitlements->canCreateProject($user);
    }
}
