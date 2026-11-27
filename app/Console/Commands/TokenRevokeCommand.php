<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Support\ApiTokens;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revokes one API token. The apps that use it lose access at once.
 */
class TokenRevokeCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:token:revoke
        {id : The id of the token (see fp:token:list)}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Revoke one API token';

    public function handle(): int
    {
        $this->prepareAgentMode();

        $id = (string) $this->argument('id');
        $token = ctype_digit($id) ? PersonalAccessToken::query()->find((int) $id) : null;

        if (! $token) {
            return $this->failWith(AgentExit::INVALID, 'token_not_found', "No API token has the id {$id}.", 'Run php artisan fp:token:list to see the tokens.');
        }

        $description = ApiTokens::describe($token);
        $token->delete();

        if (! $this->wantsJson()) {
            $this->components->info("The token \"{$description['name']}\" ({$description['id']}) is revoked.");
        }

        return $this->respond(AgentExit::SUCCESS, ['status' => 'revoked', 'token' => $description]);
    }
}
