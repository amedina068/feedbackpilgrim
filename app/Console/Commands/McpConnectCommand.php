<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Models\User;
use App\Support\ApiTokens;
use App\Support\McpConnect;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Prints the command or the config that connects an MCP client to this
 * instance. The lines read the token from FP_TOKEN, so the token is not in
 * the command text. With --token=new, the command also creates an MCP token.
 */
class McpConnectCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:mcp:connect
        {--client=claude : claude, codex, cursor or json}
        {--token= : Use "new" to also create a token with mcp:read and mcp:write}
        {--user= : The owner of the new token (default: the first owner)}
        {--project= : The subdomain of the project of the new token}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Print the command or the config that connects an MCP client to this instance';

    public function handle(): int
    {
        $this->prepareAgentMode();

        $client = strtolower((string) $this->option('client'));

        if (! in_array($client, McpConnect::CLIENTS, true)) {
            return $this->failWith(AgentExit::INVALID, 'invalid_client', "The client \"{$client}\" is not known.", 'Use --client='.implode('|', McpConnect::CLIENTS).'.');
        }

        $tokenOption = $this->option('token');

        if ($tokenOption !== null && $tokenOption !== 'new') {
            return $this->failWith(AgentExit::INVALID, 'invalid_token_option', 'The only value of --token is "new".', 'Run php artisan fp:mcp:connect --client=claude --token=new');
        }

        $token = null;

        if ($tokenOption === 'new') {
            $token = $this->newToken();

            if (is_int($token)) {
                return $token;
            }
        }

        $plainText = $token['plain_text'] ?? null;

        $data = [
            'client' => $client,
            'mcp_url' => McpConnect::url(),
            'command' => match ($client) {
                'claude' => McpConnect::claude(),
                'codex' => McpConnect::codex(),
                default => null,
            },
            'config' => match ($client) {
                'codex' => McpConnect::codexToml(),
                'cursor' => McpConnect::cursorConfig(),
                'json' => McpConnect::jsonConfig($plainText),
                default => null,
            },
            'env' => $plainText === null ? null : McpConnect::export($plainText),
            'token' => $token,
        ];

        if (! $this->wantsJson()) {
            $this->printInstructions($data);
        }

        return $this->respond(AgentExit::SUCCESS, $data);
    }

    /**
     * @return array<string, mixed>|int
     */
    private function newToken(): array|int
    {
        $email = $this->option('user');
        $user = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->where('is_owner', true)->orderBy('id')->first();

        if (! $user) {
            return $this->failWith(AgentExit::INVALID, 'user_not_found', $email ? "No user has the email {$email}." : 'The instance has no owner.', 'Give --user, or run php artisan fp:install first.');
        }

        try {
            $client = ApiTokens::projectFor($user, $this->option('project'));
        } catch (InvalidArgumentException $exception) {
            $code = $exception->getCode() === ApiTokens::PROJECT_NOT_FOUND ? 'project_not_found' : 'project_required';

            return $this->failWith(AgentExit::INVALID, $code, $exception->getMessage(), 'Give --project=<subdomain>.');
        }

        $token = ApiTokens::createProductToken($user, $client, 'mcp '.now()->format('Y-m-d H:i'), [ApiTokens::MCP_READ, ApiTokens::MCP_WRITE]);

        return array_merge(ApiTokens::describe($token->accessToken), ['plain_text' => $token->plainTextToken]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function printInstructions(array $data): void
    {
        if ($data['token']) {
            $this->components->warn('Keep this token now. The command does not show it again.');
        }

        $this->line('  1. Export the token in the shell that starts the client:');
        $this->line('     '.($data['env'] ?? 'export '.McpConnect::TOKEN_ENV."='<your token>'"));
        $this->newLine();

        if ($data['command']) {
            $this->line('  2. Run:');
            $this->line('     '.$data['command']);
        }

        if ($data['config']) {
            $this->line($data['command'] ? '  Or add this config:' : '  2. Add this config:');
            $this->line(is_string($data['config'])
                ? $data['config']
                : json_encode($data['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
