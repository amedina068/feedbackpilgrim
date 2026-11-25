<?php

namespace App\Http\Controllers;

use App\Support\ApiTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The API tokens of a project. A token from the dashboard gets only product
 * abilities. It never gets `*` or `instance:admin`.
 */
class ApiKeyController extends Controller
{
    /**
     * The access choices of the form, with their abilities.
     *
     * @var array<string, list<string>>
     */
    public const ACCESS = [
        'full' => ApiTokens::PRODUCT_ABILITIES,
        'mcp' => [ApiTokens::MCP_READ, ApiTokens::MCP_WRITE],
        'mcp_read' => [ApiTokens::MCP_READ],
        'api' => [ApiTokens::API],
    ];

    /**
     * The expiry choices of the form, in days. No value means no expiry.
     *
     * @var list<int>
     */
    public const EXPIRY_DAYS = [7, 30, 90, 365];

    public function store(Request $request): RedirectResponse
    {
        $clientId = $request->attributes->get('fp_client_id');

        if (! $clientId) {
            return back()->with('api_error', 'No client selected.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'access' => ['nullable', 'string', Rule::in(array_keys(self::ACCESS))],
            'expires_in_days' => ['nullable', 'integer', Rule::in(self::EXPIRY_DAYS)],
        ]);

        $name = trim($validated['name'] ?? '') ?: 'API token';
        $abilities = self::ACCESS[$validated['access'] ?? 'full'];
        $expiresAt = isset($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null;

        $token = $request->user()->createToken($name, $abilities, $expiresAt);

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
