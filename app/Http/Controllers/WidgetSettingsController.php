<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WidgetSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $client = $this->client($request);

        return view('settings.widget', compact('client'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'require_identity_verification' => 'sometimes|boolean',
        ]);

        $this->client($request)
            ->forceFill(['require_identity_verification' => $request->boolean('require_identity_verification')])
            ->save();

        return back()->with('success', 'Widget settings updated.');
    }

    public function regenerateIdentitySecret(Request $request): RedirectResponse
    {
        $this->client($request)->regenerateIdentitySecret();

        return back()->with('success', 'Identity secret regenerated. Update the secret in your app now.');
    }

    private function client(Request $request): Client
    {
        return Client::query()->findOrFail($request->attributes->get('fp_client_id'));
    }
}
