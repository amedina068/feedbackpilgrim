<?php

namespace App\Services;

use App\Exceptions\SendingDomainException;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the SES v2 identity API. It holds no business rules.
 * Each AWS error becomes a SendingDomainException with a safe message.
 */
class SesSendingDomainsService
{
    public const SAFE_ERROR_MESSAGE = 'Amazon SES could not complete the request. Try again in a few minutes.';

    public const APP_TAG_KEY = 'app';

    public const APP_TAG_VALUE = 'feedbackpilgrim';

    public function __construct(private SesV2Client $ses) {}

    /**
     * @return list<string>
     */
    public function create(string $domain): array
    {
        $result = $this->call('createEmailIdentity', [
            'EmailIdentity' => $domain,
            'DkimSigningAttributes' => ['NextSigningKeyLength' => 'RSA_2048_BIT'],
            'Tags' => [['Key' => self::APP_TAG_KEY, 'Value' => self::APP_TAG_VALUE]],
        ]);

        return $result['DkimAttributes']['Tokens'] ?? [];
    }

    public function setMailFrom(string $domain, string $mailFromSubdomain): void
    {
        $this->call('putEmailIdentityMailFromAttributes', [
            'EmailIdentity' => $domain,
            'MailFromDomain' => $mailFromSubdomain,
            'BehaviorOnMxFailure' => 'USE_DEFAULT_VALUE',
        ]);
    }

    /**
     * Set the MAIL FROM domain. A failure is logged and does not stop the caller,
     * because the check action applies it again.
     */
    public function setMailFromOrLog(string $domain, string $mailFromSubdomain): void
    {
        try {
            $this->setMailFrom($domain, $mailFromSubdomain);
        } catch (SendingDomainException) {
            Log::warning('MAIL FROM setup failed. The next check applies it again.', ['domain' => $domain]);
        }
    }

    /**
     * @return array{verified: bool, dkim_status: ?string, mail_from_domain: ?string, mail_from_status: ?string, tags: array<string, string>}
     */
    public function status(string $domain): array
    {
        $result = $this->call('getEmailIdentity', ['EmailIdentity' => $domain]);

        return [
            'verified' => (bool) ($result['VerifiedForSendingStatus'] ?? false),
            'dkim_status' => $result['DkimAttributes']['Status'] ?? null,
            'mail_from_domain' => $result['MailFromAttributes']['MailFromDomain'] ?? null,
            'mail_from_status' => $result['MailFromAttributes']['MailFromDomainStatus'] ?? null,
            'tags' => array_column($result['Tags'] ?? [], 'Value', 'Key'),
        ];
    }

    /**
     * Delete the identity. An identity that does not exist counts as deleted.
     */
    public function delete(string $domain): void
    {
        try {
            $this->call('deleteEmailIdentity', ['EmailIdentity' => $domain]);
        } catch (SendingDomainException $exception) {
            if ($exception->awsErrorCode !== 'NotFoundException') {
                throw $exception;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function call(string $operation, array $arguments): Result
    {
        try {
            return $this->ses->{$operation}($arguments);
        } catch (AwsException $exception) {
            Log::warning('SES sending domain request failed.', [
                'operation' => $operation,
                'domain' => $arguments['EmailIdentity'] ?? null,
                'aws_code' => $exception->getAwsErrorCode(),
                'aws_message' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            throw new SendingDomainException(self::SAFE_ERROR_MESSAGE, $exception->getAwsErrorCode(), $exception);
        }
    }
}
