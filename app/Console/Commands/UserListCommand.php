<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Mcp\Tools\Admin\ListUsersTool;
use Illuminate\Console\Command;

/**
 * Lists the users of the instance, for an operator who recovers access.
 */
class UserListCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:user:list
        {--search= : Show only the users whose name or email contains this text}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'List the users of this instance';

    public function handle(): int
    {
        $this->prepareAgentMode();

        $users = ListUsersTool::users((string) $this->option('search'));

        if (! $this->wantsJson()) {
            if ($users === []) {
                $this->components->info('No user matches.');
            } else {
                $this->table(
                    ['ID', 'Name', 'Email', 'Projects', 'Owner', 'Status', 'Created'],
                    array_map(fn (array $user) => [
                        $user['id'],
                        $user['name'],
                        $user['email'],
                        implode(', ', $user['projects']),
                        $user['owner'] ? 'yes' : 'no',
                        $user['status'],
                        substr((string) $user['created_at'], 0, 10),
                    ], $users),
                );
            }
        }

        return $this->respond(AgentExit::SUCCESS, ['users' => $users]);
    }
}
