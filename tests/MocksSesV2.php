<?php

namespace Tests;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\Exception\SesV2Exception;
use Aws\SesV2\SesV2Client;

/**
 * Bind a real SesV2Client that answers from a queue instead of AWS.
 * Queue one result or error for each SES call that the test expects.
 * The trait records each command that the client runs.
 */
trait MocksSesV2
{
    protected MockHandler $sesMock;

    /** @var list<CommandInterface> */
    protected array $sesCommands = [];

    protected function setUpMocksSesV2(): void
    {
        $this->sesMock = new MockHandler;
        $this->sesCommands = [];

        $client = new SesV2Client([
            'region' => 'us-east-1',
            'version' => 'latest',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            // The SDK retries throttling errors such as LimitExceededException.
            // One queued answer must give one call, so the mock does not retry.
            'retries' => 0,
            'handler' => $this->sesMock,
        ]);

        $client->getHandlerList()->appendInit(Middleware::tap(function (CommandInterface $command): void {
            $this->sesCommands[] = $command;
        }), 'record-commands');

        $this->app->instance(SesV2Client::class, $client);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function queueSesResult(array $data = []): void
    {
        $this->sesMock->append(new Result($data));
    }

    protected function queueSesError(string $code, string $message = 'AWS error'): void
    {
        $this->sesMock->append(fn (CommandInterface $command) => new SesV2Exception($message, $command, ['code' => $code]));
    }

    /**
     * @return list<string>
     */
    protected function sesCommandNames(): array
    {
        return array_map(fn (CommandInterface $command) => $command->getName(), $this->sesCommands);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sesCommandParams(string $name): array
    {
        foreach ($this->sesCommands as $command) {
            if ($command->getName() === $name) {
                return $command->toArray();
            }
        }

        $this->fail("SES command {$name} did not run.");
    }
}
