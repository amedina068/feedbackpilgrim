<?php

namespace App\Actions\SendingDomains;

use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Connect a sending domain for a client. The action creates the SES identity,
 * sets the MAIL FROM domain, and then saves a pending row. A lock per domain
 * stops two requests from connecting the same domain at the same time.
 */
class ConnectSendingDomain
{
    use LocksSendingDomain;

    public const ALREADY_CONNECTED_MESSAGE = 'This domain is already connected.';

    public const CLIENT_HAS_DOMAIN_MESSAGE = 'This client already has a connected domain.';

    public function __construct(private SesSendingDomainsService $ses) {}

    public function __invoke(int $clientId, string $domain, string $senderLocalPart): SendingDomain
    {
        return $this->withDomainLock($domain, fn (): SendingDomain => $this->connect($clientId, $domain, $senderLocalPart), self::ALREADY_CONNECTED_MESSAGE);
    }

    private function connect(int $clientId, string $domain, string $senderLocalPart): SendingDomain
    {
        if (SendingDomain::query()->where('domain', $domain)->exists()) {
            throw new SendingDomainException(self::ALREADY_CONNECTED_MESSAGE);
        }

        $mailFromSubdomain = "bounce.{$domain}";

        $tokens = $this->createIdentity($domain);

        $this->ses->setMailFromOrLog($domain, $mailFromSubdomain);

        try {
            return SendingDomain::create([
                'client_id' => $clientId,
                'domain' => $domain,
                'sender_local_part' => $senderLocalPart,
                'mail_from_subdomain' => $mailFromSubdomain,
                'status' => SendingDomainStatus::Pending,
                'dkim_tokens' => $tokens,
                'verified_at' => null,
            ]);
        } catch (Throwable $exception) {
            $this->rollBackIdentity($domain);

            if ($exception instanceof UniqueConstraintViolationException) {
                $message = SendingDomain::query()->where('client_id', $clientId)->exists()
                    ? self::CLIENT_HAS_DOMAIN_MESSAGE
                    : self::ALREADY_CONNECTED_MESSAGE;

                throw new SendingDomainException($message, null, $exception);
            }

            throw $exception;
        }
    }

    /**
     * Delete the identity after a failed insert. A failed delete is logged and
     * does not hide the insert error. The tag rule cleans up the orphan later.
     */
    private function rollBackIdentity(string $domain): void
    {
        try {
            $this->ses->delete($domain);
        } catch (Throwable $exception) {
            Log::warning('Could not roll back the SES identity after a failed insert.', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Create the identity. The caller holds the domain lock and has checked that
     * no row has this domain. So an existing identity with our tag is an orphan
     * from an earlier partial failure. It is deleted and created again, and the
     * new owner gets new DKIM tokens. An identity that is not ours is never deleted.
     *
     * @return list<string>
     */
    private function createIdentity(string $domain): array
    {
        try {
            return $this->ses->create($domain);
        } catch (SendingDomainException $exception) {
            if ($exception->awsErrorCode !== 'AlreadyExistsException') {
                throw $exception;
            }
        }

        $tags = $this->ses->status($domain)['tags'];

        if (($tags[SesSendingDomainsService::APP_TAG_KEY] ?? null) !== SesSendingDomainsService::APP_TAG_VALUE) {
            throw new SendingDomainException(self::ALREADY_CONNECTED_MESSAGE);
        }

        $this->ses->delete($domain);

        return $this->ses->create($domain);
    }
}
