<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Support\InstanceDoctor;
use Illuminate\Console\Command;

/**
 * Checks the health of the instance and gives a fix for each problem.
 */
class DoctorCommand extends Command
{
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:doctor
        {--fix : Run the safe fixes (today: the public storage link)}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Check the health of this instance and show a fix for each problem';

    public function handle(InstanceDoctor $doctor): int
    {
        $this->prepareAgentMode();

        $checks = $doctor->run((bool) $this->option('fix'));
        $data = ['healthy' => ! InstanceDoctor::hasFailure($checks), 'checks' => $checks];

        if (! $this->wantsJson()) {
            foreach ($checks as $check) {
                $label = strtoupper($check['status']).'  '.$check['id'];
                $this->components->twoColumnDetail($label, $check['message']);

                if ($check['status'] !== 'ok' && $check['fix'] !== null) {
                    $this->line('        Fix: '.$check['fix']);
                }
            }
        }

        $errors = array_values(array_map(
            fn (array $check) => AgentExit::error($check['id'], $check['message'], $check['fix']),
            array_filter($checks, fn (array $check) => $check['status'] === 'fail'),
        ));

        if ($errors === []) {
            return $this->respond(AgentExit::SUCCESS, $data);
        }

        if ($this->wantsJson()) {
            return $this->respond(AgentExit::FAILURE, $data, $errors);
        }

        $this->newLine();
        $this->components->error(count($errors).' checks failed.');

        return AgentExit::FAILURE;
    }
}
