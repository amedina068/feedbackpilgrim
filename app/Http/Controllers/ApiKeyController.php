<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiKeyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $clientId = $request->attributes->get('fp_client_id');

        if (! $clientId) {
            return back()->with('api_error', 'No client selected.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $name = trim($validated['name'] ?? '') ?: 'API token';

        $token = $request->user()->createToken($name, ['*']);

        $token->accessToken->client_id = $clientId;
        $token->accessToken->save();

        return back()->with([
            'api_success' => "API token \"{$name}\" created.",
            'new_token' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        $clientId = $request->attributes->get('fp_client_id');

        if ((int) $token->client_id !== (int) $clientId) {
            abort(404);
        }

        $token->delete();

        return back()->with('api_success', 'API token deleted.');
    }
}
