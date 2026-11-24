<?php

namespace App\Actions\SendingDomains;

use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;

/**
 * Read the SES identity and save its status on the row. The scheduled
 * command and the "Check now" button call this action.
 */
class CheckSendingDomain
{
    public function __construct(private SesSendingDomainsService $ses) {}

    public function __invoke(SendingDomain $sendingDomain): SendingDomain
    {
        try {
            $identity = $this->ses->status($sendingDomain->domain);
        } catch (SendingDomainException $exception) {
            if ($exception->awsErrorCode !== 'NotFoundException') {
                throw $exception;
            }

            $sendingDomain->update(['status' => SendingDomainStatus::Failed, 'last_checked_at' => now()]);

            return $sendingDomain;
        }

        if ($identity['mail_from_domain'] === null) {
            $this->ses->setMailFromOrLog($sendingDomain->domain, $sendingDomain->mail_from_subdomain);
        }

        $sendingDomain->update([
            'status' => $this->statusFor($identity['verified'], $identity['dkim_status']),
            'verified_at' => $identity['verified'] ? ($sendingDomain->verified_at ?? now()) : $sendingDomain->verified_at,
            'dkim_status' => $identity['dkim_status'],
            'mail_from_status' => $identity['mail_from_status'],
            'last_checked_at' => now(),
        ]);

        return $sendingDomain;
    }

    private function statusFor(bool $verified, ?string $dkimStatus): SendingDomainStatus
    {
        if ($verified) {
            return SendingDomainStatus::Verified;
        }

        if ($dkimStatus === 'FAILED') {
            return SendingDomainStatus::Failed;
        }

        return SendingDomainStatus::Pending;
    }
}
