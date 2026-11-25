<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Creates an API token from the command line.
 *
 * A product token has the abilities mcp:read, mcp:write and api, and belongs
 * to one project. With --admin, the command creates an owner token with only
 * the instance:admin ability, for the /mcp/admin route. That token expires in
 * 7 days or less.
 */
class TokenCreateCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:token:create
        {--user= : The email of the user who owns the token}
        {--name= : A name for the token}
        {--project= : The subdomain of the project (required when the user has two or more)}
        {--abilities= : A comma-separated list of mcp:read, mcp:write and api (default: all three)}
        {--expires= : never, a number of hours (12h), a number of days (30d) or a date (2027-01-31). Default: never, or 1d with --admin}
        {--admin : Create an instance:admin token for an owner, for the /mcp/admin route}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Create an API token with scoped abilities';

    public function handle(): int
    {
        $this->prepareAgentMode();

        $email = (string) $this->option('user');
        $name = trim((string) $this->option('name'));

        if ($email === '' || $name === '') {
            return $this->failWith(AgentExit::INVALID, 'missing_option', 'Give --user and --name.', 'Run php artisan fp:token:create --user=you@example.com --name=agent --json');
        }

        if (mb_strlen($name) > 100) {
            return $this->failWith(AgentExit::INVALID, 'invalid_name', 'The name must have 100 characters or fewer.');
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return $this->failWith(AgentExit::INVALID, 'user_not_found', "No user has the email {$email}.", 'Run php artisan fp:user:list to see the users.');
        }

        $abilities = $this->option('abilities') === null
            ? null
            : explode(',', (string) $this->option('abilities'));

        return $this->option('admin')
            ? $this->createAdminToken($user, $name)
            : $this->createProductToken($user, $name, $abilities);
    }

    /**
     * @param  list<string>|null  $abilities
     */
    private function createProductToken(User $user, string $name, ?array $abilities): int
    {
        try {
            $abilities = ApiTokens::validProductAbilities($abilities ?? ApiTokens::PRODUCT_ABILITIES);
        } catch (InvalidArgumentException $exception) {
            return $this->failWith(AgentExit::INVALID, 'invalid_abilities', $exception->getMessage(), 'For an admin token, use --admin.');
        }

        try {
            $expiresAt = ApiTokens::parseExpiry($this->option('expires'));
        } catch (InvalidArgumentException $exception) {
            return $this->failWith(AgentExit::INVALID, 'invalid_expiry', $exception->getMessage());
        }

        try {
            $client = ApiTokens::projectFor($user, $this->option('project'));
        } catch (InvalidArgumentException $exception) {
            $code = $exception->getCode() === ApiTokens::PROJECT_NOT_FOUND ? 'project_not_found' : 'project_required';

            return $this->failWith(AgentExit::INVALID, $code, $exception->getMessage(), 'Give --project=<subdomain>.');
        }

        $token = ApiTokens::createProductToken($user, $client, $name, $abilities, $expiresAt);

        return $this->created(array_merge(ApiTokens::describe($token->accessToken), ['plain_text' => $token->plainTextToken]));
    }

    private function createAdminToken(User $user, string $name): int
    {
        if (! $user->isOwner() || $user->isDeactivated()) {
            return $this->failWith(AgentExit::INVALID, 'not_owner', "{$user->email} is not an active owner. Only an owner can have an admin token.");
        }

        try {
            $expiresAt = ApiTokens::parseExpiry($this->option('expires') ?? '1d');

            if ($expiresAt === null) {
                throw new InvalidArgumentException('An admin token must expire in '.ApiTokens::ADMIN_MAX_DAYS.' days or less.');
            }

            $token = ApiTokens::createAdminToken($user, $name, $expiresAt);
        } catch (InvalidArgumentException $exception) {
            return $this->failWith(AgentExit::INVALID, 'invalid_expiry', $exception->getMessage(), 'Use --expires=1d, or a value up to '.ApiTokens::ADMIN_MAX_DAYS.'d.');
        }

        return $this->created(array_merge(ApiTokens::describe($token->accessToken), ['plain_text' => $token->plainTextToken]));
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function created(array $token): int
    {
        if (! $this->wantsJson()) {
            $this->components->info("The token \"{$token['name']}\" is ready.");
            $this->components->twoColumnDetail('Token', $token['plain_text']);
            $this->components->twoColumnDetail('Abilities', implode(', ', $token['abilities']));
            $this->components->twoColumnDetail('Expires', $token['expires_at'] ?? 'never');
            $this->components->warn('Keep this token now. The command does not show it again.');
        }

        return $this->respond(AgentExit::SUCCESS, ['token' => $token]);
    }
}
