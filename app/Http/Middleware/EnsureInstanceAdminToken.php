<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiTokens;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only a short-lived owner token reach the admin MCP server over HTTP.
 *
 * The token must have exactly the `instance:admin` ability. A token with `*`
 * does not count, so an old product token never reaches admin power. The
 * token must expire in 7 days or less after it was made, and its user must be
 * an active owner.
 */
class EnsureInstanceAdminToken
{
    public const MESSAGE = 'The admin MCP server needs an owner token with only the instance:admin ability that expires in '
        .ApiTokens::ADMIN_MAX_DAYS.' days or less. Make one with: php artisan fp:token:create --admin --user=<owner email>';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('mcp.admin.http_enabled')) {
            abort(404);
        }

        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken || ! self::allows($user, $token)) {
            return response()->json(['message' => self::MESSAGE, 'error' => 'insufficient_ability'], 403);
        }

        return $next($request);
    }

    private static function allows(User $user, PersonalAccessToken $token): bool
    {
        return array_values((array) $token->abilities) === [ApiTokens::INSTANCE_ADMIN]
            && $token->expires_at !== null
            && $token->created_at !== null
            && $token->expires_at->lessThanOrEqualTo($token->created_at->copy()->addDays(ApiTokens::ADMIN_MAX_DAYS)->addMinute())
            && $user->isOwner()
            && ! $user->isDeactivated();
    }
}
