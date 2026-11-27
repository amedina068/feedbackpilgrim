<?php

namespace App\Console\Concerns;

/**
 * The exit codes and the error shape of the agent contract.
 */
final class AgentExit
{
    /** The version of the JSON shape. Increase it only for a change that breaks a reader. */
    public const JSON_SCHEMA = 1;

    public const SUCCESS = 0;

    public const FAILURE = 1;

    public const INVALID = 2;

    public const CONFLICT = 3;

    /**
     * @return array{code: string, message: string, fix: string|null}
     */
    public static function error(string $code, string $message, ?string $fix = null): array
    {
        return ['code' => $code, 'message' => $message, 'fix' => $fix];
    }
}
