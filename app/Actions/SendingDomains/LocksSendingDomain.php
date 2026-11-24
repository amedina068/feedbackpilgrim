<?php

namespace App\Actions\SendingDomains;

use App\Exceptions\SendingDomainException;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Every action which changes the SES identity of a domain must use this trait.
 * It holds one cache lock per domain, so two requests cannot change the same
 * identity at the same time.
 */
trait LocksSendingDomain
{
    /**
     * Run the callback while the action holds the lock of the domain. The lock
     * lasts 60 seconds. If the lock stays busy for 5 seconds, the action throws a
     * SendingDomainException with the timeout message.
     */
    protected function withDomainLock(string $domain, Closure $callback, string $timeoutMessage): mixed
    {
        try {
            return Cache::lock("sending-domain:{$domain}", 60)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw new SendingDomainException($timeoutMessage);
        }
    }
}
