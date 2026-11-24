<?php

namespace App\Actions\SendingDomains;

use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;

/**
 * Start again for a failed domain. The action deletes the SES identity, creates
 * it again with new DKIM tokens, and sets the row back to pending. It holds the
 * same per-domain lock as ConnectSendingDomain through LocksSendingDomain.
 */
class RetrySendingDomain
{
    use LocksSendingDomain;

    public const ONLY_FAILED_MESSAGE = 'Only a failed domain can be retried.';

    public const REMOVED_MESSAGE = 'This domain was removed.';

    public function __construct(private SesSendingDomainsService $ses) {}

    public function __invoke(SendingDomain $sendingDomain): SendingDomain
    {
        return $this->withDomainLock($sendingDomain->domain, fn (): SendingDomain => $this->retry($sendingDomain), SesSendingDomainsService::SAFE_ERROR_MESSAGE);
    }

    private function retry(SendingDomain $sendingDomain): SendingDomain
    {
        if (! SendingDomain::query()->whereKey($sendingDomain->getKey())->exists()) {
            throw new SendingDomainException(self::REMOVED_MESSAGE);
        }

        if ($sendingDomain->refresh()->status !== SendingDomainStatus::Failed) {
            throw new SendingDomainException(self::ONLY_FAILED_MESSAGE);
        }

        $this->ses->delete($sendingDomain->domain);
        $tokens = $this->ses->create($sendingDomain->domain);

        $this->ses->setMailFromOrLog($sendingDomain->domain, $sendingDomain->mail_from_subdomain);

        $sendingDomain->update([
            'dkim_tokens' => $tokens,
            'status' => SendingDomainStatus::Pending,
            'verified_at' => null,
            'dkim_status' => null,
            'mail_from_status' => null,
            'last_checked_at' => null,
        ]);

        return $sendingDomain;
    }
}
