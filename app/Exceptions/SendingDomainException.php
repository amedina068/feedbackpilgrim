<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A sending domain operation failed. The message is safe to show to the user.
 * The raw AWS error is in the log, not in this message.
 */
class SendingDomainException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $awsErrorCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
