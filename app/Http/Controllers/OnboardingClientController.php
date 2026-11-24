<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClientForOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnboardingClientController extends Controller
{
    public function create(): View
    {
        return view('onboarding.client');
    }

    public function store(Request $request, CreateClientForOwner $createClient): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string'],
            'subdomain' => CreateClientForOwner::subdomainRules(),
        ], [
            'subdomain.not_in' => 'This subdomain is reserved. Choose a different subdomain.',
        ]);

        $client = $createClient(Auth::user(), (string) $request->input('name'), (string) $request->input('subdomain'));

        session(['fp_client_id' => $client->id]);

        return redirect('dashboard');
    }
}
