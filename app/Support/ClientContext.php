<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Single source of token -> identity resolution. Both the HTTP transport
 * (ApiClientMiddleware) and the MCP transport build a ClientContext from the
 * authenticated Sanctum token; client_id is never accepted as request input.
 * The dashboard builds it from the session client, so it can call the same Actions.
 */
readonly class ClientContext
{
    public function __construct(
        public int $clientId,
        public int $userId,
        public ?PersonalAccessToken $token = null,
    ) {}

    /**
     * The client that GrabClientIdFromSessionMiddleware chose, never request input.
     */
    public static function fromDashboard(Request $request): self
    {
        return new self(
            clientId: (int) $request->attributes->get('fp_client_id'),
            userId: (int) $request->user()->id,
        );
    }

    public static function fromToken(mixed $token): self
    {
        // Only real personal access tokens carry a client_id. A TransientToken
        // (session/cookie auth) or null must be rejected — bearer tokens only.
        if (! $token instanceof PersonalAccessToken || ! $token->client_id) {
            throw new UnauthorizedHttpException('sanctum', 'Invalid API token');
        }

        if (! self::tokenUserIsMemberOfTokenClient($token)) {
            throw new UnauthorizedHttpException('sanctum', 'Invalid API token');
        }

        return new self(
            clientId: (int) $token->client_id,
            userId: (int) $token->tokenable_id,
            token: $token,
        );
    }

    public function user(): User
    {
        return $this->token?->tokenable ?? User::query()->findOrFail($this->userId);
    }

    /**
     * A token is valid only while its user is a member of the token client.
     */
    private static function tokenUserIsMemberOfTokenClient(PersonalAccessToken $token): bool
    {
        $user = $token->tokenable;

        return $user instanceof User
            && $user->clients()->whereKey($token->client_id)->exists();
    }
}
