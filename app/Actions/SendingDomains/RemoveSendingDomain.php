<?php

namespace App\Actions\SendingDomains;

use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;
use Illuminate\Support\Facades\Log;

/**
 * Remove a sending domain. The action holds the per-domain lock from
 * LocksSendingDomain for the whole sequence. The row is deleted first, so
 * emails go back to the default sender at once. Then the SES identity is
 * deleted. If SES fails, the error is logged and the row stays deleted. The tag
 * rule cleans up the orphan. If the lock is busy, nothing is deleted.
 */
class RemoveSendingDomain
{
    use LocksSendingDomain;

    public function __construct(private SesSendingDomainsService $ses) {}

    public function __invoke(SendingDomain $sendingDomain): void
    {
        $this->withDomainLock($sendingDomain->domain, fn () => $this->remove($sendingDomain), SesSendingDomainsService::SAFE_ERROR_MESSAGE);
    }

    private function remove(SendingDomain $sendingDomain): void
    {
        $sendingDomain->delete();

        try {
            $this->ses->delete($sendingDomain->domain);
        } catch (SendingDomainException $exception) {
            Log::warning('Could not remove the SES identity after the domain was deleted.', [
                'domain' => $sendingDomain->domain,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
