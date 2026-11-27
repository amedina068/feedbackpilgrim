<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Support\InstanceSetup;
use Illuminate\Console\Command;

/**
 * Prints the URL of the web setup page while the instance has no account.
 *
 * The URL holds the setup token. The Docker image prints it to the log of the
 * web container when the container starts.
 */
class SetupUrlCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:setup-url
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Print the URL of the setup page that creates the owner account';

    /**
     * Without --json, the output stays one line with the URL. The Docker
     * entrypoint reads that line.
     */
    public function handle(): int
    {
        $this->prepareAgentMode();

        $needed = InstanceSetup::isNeeded();
        $url = $needed ? InstanceSetup::url() : null;

        if (! $this->wantsJson()) {
            if ($url === null) {
                $this->components->info('An account already exists. The setup page is closed.');
            } else {
                $this->line($url);
            }
        }

        return $this->respond(AgentExit::SUCCESS, ['setup_needed' => $needed, 'url' => $url]);
    }
}
