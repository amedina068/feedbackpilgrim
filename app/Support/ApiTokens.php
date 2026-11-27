<?php

namespace App\Support;

use App\Models\Client;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The abilities and the expiry of API tokens.
 *
 * A product token belongs to one project and has only product abilities. It
 * never gets `instance:admin`. An admin token belongs to an owner, has no
 * project, has only `instance:admin` and expires in 7 days or less.
 *
 * Tokens from before the abilities have `*`. Sanctum gives `*` every product
 * ability, so they keep working. The admin surface rejects `*`.
 */
class ApiTokens
{
    /** Read with the MCP server. */
    public const MCP_READ = 'mcp:read';

    /** Write with the MCP server. */
    public const MCP_WRITE = 'mcp:write';

    /** Read and write with the REST API. */
    public const API = 'api';

    /** Run the instance-admin MCP server over HTTP. */
    public const INSTANCE_ADMIN = 'instance:admin';

    public const PRODUCT_ABILITIES = [self::MCP_READ, self::MCP_WRITE, self::API];

    public const ADMIN_MAX_DAYS = 7;

    /** The exception code of projectFor() when the user has no such project. */
    public const PROJECT_NOT_FOUND = 1;

    /** The exception code of projectFor() when the user has two or more projects. */
    public const PROJECT_REQUIRED = 2;

    /**
     * The project of a new token: the given subdomain, or the only project of
     * the user.
     *
     * @throws InvalidArgumentException with the code PROJECT_NOT_FOUND or PROJECT_REQUIRED
     */
    public static function projectFor(User $user, ?string $subdomain): Client
    {
        $clients = Client::query()
            ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
            ->orderBy('id')
            ->get();
        $known = 'The projects of this user: '.($clients->pluck('slug')->join(', ') ?: 'none').'.';

        if ($subdomain !== null && $subdomain !== '') {
            return $clients->firstWhere('slug', $subdomain)
                ?? throw new InvalidArgumentException("The user {$user->email} has no project with the subdomain {$subdomain}. {$known}", self::PROJECT_NOT_FOUND);
        }

        if ($clients->count() !== 1) {
            throw new InvalidArgumentException("The user {$user->email} has {$clients->count()} projects. Give the project. {$known}", self::PROJECT_REQUIRED);
        }

        return $clients->first();
    }

    /**
     * @param  list<string>  $abilities
     *
     * @throws InvalidArgumentException
     */
    public static function createProductToken(User $user, Client $client, string $name, array $abilities = self::PRODUCT_ABILITIES, ?CarbonInterface $expiresAt = null): NewAccessToken
    {
        $abilities = self::validProductAbilities($abilities);

        if (! $user->clients()->whereKey($client->id)->exists()) {
            throw new InvalidArgumentException("The user {$user->email} is not a member of the project {$client->slug}.");
        }

        $token = $user->createToken($name, $abilities, $expiresAt);
        $token->accessToken->forceFill(['client_id' => $client->id])->save();

        return $token;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function createAdminToken(User $owner, string $name, CarbonInterface $expiresAt): NewAccessToken
    {
        if (! $owner->isOwner() || $owner->isDeactivated()) {
            throw new InvalidArgumentException('Only an active owner can have an instance:admin token.');
        }

        if ($expiresAt->isPast() || $expiresAt->greaterThan(now()->addDays(self::ADMIN_MAX_DAYS)->addMinute())) {
            throw new InvalidArgumentException('An instance:admin token must expire in '.self::ADMIN_MAX_DAYS.' days or less.');
        }

        return $owner->createToken($name, [self::INSTANCE_ADMIN], $expiresAt);
    }

    /**
     * @param  array<int, string>  $abilities
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    public static function validProductAbilities(array $abilities): array
    {
        $abilities = array_values(array_unique(array_filter(array_map('trim', $abilities))));

        if ($abilities === []) {
            throw new InvalidArgumentException('Give one or more abilities: '.implode(', ', self::PRODUCT_ABILITIES).'.');
        }

        $unknown = array_diff($abilities, self::PRODUCT_ABILITIES);

        if ($unknown !== []) {
            throw new InvalidArgumentException('These abilities are unknown or not allowed: '.implode(', ', $unknown).'. The allowed abilities are '.implode(', ', self::PRODUCT_ABILITIES).'.');
        }

        return $abilities;
    }

    /**
     * Read an expiry such as `never`, `12h`, `30d` or `2027-01-31`. Returns
     * null for no expiry.
     *
     * @throws InvalidArgumentException
     */
    public static function parseExpiry(?string $value): ?CarbonInterface
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || $value === 'never') {
            return null;
        }

        if (preg_match('/^(\d+)([hd])$/', $value, $matches)) {
            $amount = (int) $matches[1];

            if ($amount < 1) {
                throw new InvalidArgumentException('The expiry must be 1 hour or more.');
            }

            return $matches[2] === 'h' ? now()->addHours($amount) : now()->addDays($amount);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && ($date = Carbon::createFromFormat('!Y-m-d', $value)) !== null) {
            $date = $date->endOfDay();

            if ($date->isPast()) {
                throw new InvalidArgumentException('The expiry date is in the past.');
            }

            return $date;
        }

        throw new InvalidArgumentException("The expiry \"{$value}\" is not known. Use never, a number of hours (12h), a number of days (30d) or a date (2027-01-31).");
    }

    public static function isAdminToken(PersonalAccessToken $token): bool
    {
        return in_array(self::INSTANCE_ADMIN, (array) $token->abilities, true);
    }

    public static function isLegacy(PersonalAccessToken $token): bool
    {
        return in_array('*', (array) $token->abilities, true);
    }

    /**
     * The view of a token for the CLI and the admin MCP server. It never
     * holds the token hash.
     *
     * @return array{id: int, name: string, user: string|null, project: string|null, abilities: list<string>, legacy: bool, expires_at: string|null, expired: bool, last_used_at: string|null, created_at: string|null}
     */
    public static function describe(PersonalAccessToken $token): array
    {
        $user = $token->tokenable;
        $project = $token->client_id ? Client::query()->find($token->client_id) : null;

        return [
            'id' => (int) $token->id,
            'name' => (string) $token->name,
            'user' => $user instanceof User ? $user->email : null,
            'project' => $project?->slug,
            'abilities' => array_values((array) $token->abilities),
            'legacy' => self::isLegacy($token),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'expired' => $token->expires_at !== null && $token->expires_at->isPast(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
