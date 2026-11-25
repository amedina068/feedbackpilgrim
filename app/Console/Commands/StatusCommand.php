<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Support\InstanceStatus;
use Illuminate\Console\Command;

/**
 * Shows the state of the instance in one call, for a person or an agent.
 */
class StatusCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:status
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Show the version, the database, the queue, the scheduler, the mail driver and the owner of this instance';

    public function handle(InstanceStatus $status): int
    {
        $this->prepareAgentMode();

        $data = $status->collect();
        $errors = [];

        if (! $data['database']['reachable']) {
            $errors[] = AgentExit::error('db_unreachable', 'The database is not reachable: '.$data['database']['error'], 'Check DB_CONNECTION and the DB_* values. Then run php artisan fp:doctor.');
        }

        if (! $this->wantsJson()) {
            $this->printStatus($data);
        }

        return $errors === []
            ? $this->respond(AgentExit::SUCCESS, $data)
            : $this->failMany(AgentExit::FAILURE, $errors, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function printStatus(array $data): void
    {
        $database = $data['database'];
        $queue = $data['queue'];

        $rows = [
            'Version' => $data['version'],
            'Environment' => $data['environment'],
            'App URL' => $data['app_url'],
            'Database' => $database['driver'].($database['reachable'] ? ', reachable' : ', NOT reachable'),
            'Pending migrations' => $database['pending_migrations'] ?? 'unknown',
            'Queue' => $queue['connection'].', last heartbeat '.($queue['last_heartbeat_at'] ?? 'never'),
            'Failed jobs' => $queue['failed_jobs'] ?? 'unknown',
            'Scheduler' => 'last run '.($data['scheduler']['last_run_at'] ?? 'never'),
            'Storage link' => $data['storage_link'] ? 'yes' : 'no',
            'Mail' => $data['mail']['mailer'],
            'MCP' => $data['mcp']['url'],
            'Owner' => $data['owner_exists'] ? 'yes' : 'no',
        ];

        foreach ($rows as $label => $value) {
            $this->components->twoColumnDetail($label, (string) $value);
        }

        if ($data['setup_url']) {
            $this->newLine();
            $this->line('  Setup URL: '.$data['setup_url']);
        }
    }
}
