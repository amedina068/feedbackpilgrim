<?php

namespace App\Console\Concerns;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The agent contract of the `fp:` commands.
 *
 * With `--json`, stdout holds only one JSON object:
 * `{"ok", "command", "schema", "data", "errors"}`. Messages for a person go
 * to stderr. The exit codes are 0 (success or no change), 1 (a check or an
 * action failed), 2 (bad input) and 3 (conflict).
 */
trait InteractsWithAgents
{
    protected function wantsJson(): bool
    {
        return (bool) $this->option('json');
    }

    /**
     * A command in JSON mode never prompts.
     */
    protected function prepareAgentMode(): void
    {
        if ($this->wantsJson()) {
            $this->input->setInteractive(false);
        }
    }

    /**
     * Write the JSON envelope in JSON mode, and return the exit code.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{code: string, message: string, fix: string|null}>  $errors
     */
    protected function respond(int $exitCode, array $data = [], array $errors = []): int
    {
        if ($this->wantsJson()) {
            $this->output->writeln(json_encode([
                'ok' => $exitCode === 0,
                'command' => $this->getName(),
                'schema' => AgentExit::JSON_SCHEMA,
                'data' => (object) $data,
                'errors' => $errors,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        }

        return $exitCode;
    }

    /**
     * Report one error. In JSON mode, the envelope carries it. Otherwise the
     * command prints the message and the fix.
     *
     * @param  array<string, mixed>  $data
     */
    protected function failWith(int $exitCode, string $code, string $message, ?string $fix = null, array $data = []): int
    {
        return $this->failMany($exitCode, [AgentExit::error($code, $message, $fix)], $data);
    }

    /**
     * @param  list<array{code: string, message: string, fix: string|null}>  $errors
     * @param  array<string, mixed>  $data
     */
    protected function failMany(int $exitCode, array $errors, array $data = []): int
    {
        if (! $this->wantsJson()) {
            foreach ($errors as $error) {
                $this->components->error($error['message']);

                if ($error['fix'] !== null) {
                    $this->line('  Fix: '.$error['fix']);
                }
            }
        }

        return $this->respond($exitCode, $data, $errors);
    }

    /**
     * A message for a person. In JSON mode it goes to stderr, so that stdout
     * holds only the JSON object. Without a stderr stream, the message is dropped.
     */
    protected function note(string $message): void
    {
        if (! $this->wantsJson()) {
            $this->components->info($message);

            return;
        }

        $output = $this->output->getOutput();

        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln($message);
        }
    }
}
