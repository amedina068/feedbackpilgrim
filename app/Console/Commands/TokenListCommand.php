<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Support\ApiTokens;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Lists the API tokens of the instance. It never shows a token value.
 */
class TokenListCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:token:list
        {--user= : Show only the tokens of the user with this email}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'List the API tokens with their abilities and expiry';

    public function handle(): int
    {
        $this->prepareAgentMode();

        $email = $this->option('user');

        $tokens = PersonalAccessToken::query()
            ->with('tokenable')
            ->orderBy('id')
            ->get()
            ->map(fn (PersonalAccessToken $token) => ApiTokens::describe($token))
            ->filter(fn (array $token) => $email === null || $token['user'] === $email)
            ->values()
            ->all();

        if (! $this->wantsJson()) {
            if ($tokens === []) {
                $this->components->info('No API token matches.');
            } else {
                $this->table(
                    ['ID', 'Name', 'User', 'Project', 'Abilities', 'Expires', 'Last used'],
                    array_map(fn (array $token) => [
                        $token['id'],
                        $token['name'],
                        $token['user'],
                        $token['project'] ?? '-',
                        $token['legacy'] ? '* (old token, full access)' : implode(', ', $token['abilities']),
                        $token['expires_at'] ?? 'never',
                        $token['last_used_at'] ?? 'never',
                    ], $tokens),
                );
            }
        }

        return $this->respond(AgentExit::SUCCESS, ['tokens' => $tokens]);
    }
}
