<?php

namespace Tests\Feature\Agent;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs an `fp:` command with `--json` and checks the envelope of the agent
 * contract: stdout holds one JSON object with five keys.
 */
trait RunsJsonCommands
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array{exit: int, ok: bool, data: array<string, mixed>, errors: list<array{code: string, message: string, fix: string|null}>}
     */
    protected function runJson(string $command, array $parameters = []): array
    {
        $exit = Artisan::call($command, array_merge($parameters, ['--json' => true, '--no-interaction' => true]));
        $output = Artisan::output();
        $json = json_decode($output, true);

        $this->assertIsArray($json, "The output of {$command} is not one JSON object:\n{$output}");
        $this->assertSame(['ok', 'command', 'schema', 'data', 'errors'], array_keys($json));
        $this->assertSame($command, $json['command']);
        $this->assertSame(1, $json['schema']);
        $this->assertSame($exit === 0, $json['ok']);

        foreach ($json['errors'] as $error) {
            $this->assertSame(['code', 'message', 'fix'], array_keys($error));
        }

        return ['exit' => $exit, 'ok' => $json['ok'], 'data' => $json['data'], 'errors' => $json['errors']];
    }
}
