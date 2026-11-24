<?php

namespace Tests\Feature\SendingDomains;

use App\Exceptions\SendingDomainException;
use App\Services\SesSendingDomainsService;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Facades\Log;
use Tests\MocksSesV2;
use Tests\TestCase;

class SesSendingDomainsServiceTest extends TestCase
{
    use MocksSesV2;

    public function test_create_requests_2048_bit_dkim_with_the_app_tag_and_returns_the_tokens(): void
    {
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);

        $tokens = app(SesSendingDomainsService::class)->create('acme.com');

        $this->assertSame(['t1', 't2', 't3'], $tokens);
        $params = $this->sesCommandParams('CreateEmailIdentity');
        $this->assertSame('acme.com', $params['EmailIdentity']);
        $this->assertSame('RSA_2048_BIT', $params['DkimSigningAttributes']['NextSigningKeyLength']);
        $this->assertSame([['Key' => 'app', 'Value' => 'feedbackpilgrim']], $params['Tags']);
    }

    public function test_set_mail_from_uses_the_default_value_on_mx_failure(): void
    {
        $this->queueSesResult();

        app(SesSendingDomainsService::class)->setMailFrom('acme.com', 'bounce.acme.com');

        $params = $this->sesCommandParams('PutEmailIdentityMailFromAttributes');
        $this->assertSame('acme.com', $params['EmailIdentity']);
        $this->assertSame('bounce.acme.com', $params['MailFromDomain']);
        $this->assertSame('USE_DEFAULT_VALUE', $params['BehaviorOnMxFailure']);
    }

    public function test_aws_errors_become_a_safe_exception_and_the_raw_message_is_logged(): void
    {
        Log::spy();
        $this->queueSesError('LimitExceededException', 'secret internal detail arn:aws:ses:123');

        try {
            app(SesSendingDomainsService::class)->create('acme.com');
            $this->fail('Expected a SendingDomainException.');
        } catch (SendingDomainException $exception) {
            $this->assertStringNotContainsString('secret internal detail', $exception->getMessage());
            $this->assertSame('LimitExceededException', $exception->awsErrorCode);
        }

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => str_contains($context['aws_message'] ?? '', 'secret internal detail')
        )->once();
    }

    public function test_status_returns_the_verification_dkim_mail_from_and_tags(): void
    {
        $this->queueSesResult([
            'VerifiedForSendingStatus' => true,
            'DkimAttributes' => ['Status' => 'SUCCESS'],
            'MailFromAttributes' => ['MailFromDomain' => 'bounce.acme.com', 'MailFromDomainStatus' => 'PENDING'],
            'Tags' => [['Key' => 'app', 'Value' => 'feedbackpilgrim']],
        ]);

        $status = app(SesSendingDomainsService::class)->status('acme.com');

        $this->assertSame('acme.com', $this->sesCommandParams('GetEmailIdentity')['EmailIdentity']);
        $this->assertSame([
            'verified' => true,
            'dkim_status' => 'SUCCESS',
            'mail_from_domain' => 'bounce.acme.com',
            'mail_from_status' => 'PENDING',
            'tags' => ['app' => 'feedbackpilgrim'],
        ], $status);
    }

    public function test_status_has_safe_defaults_when_ses_returns_no_attributes(): void
    {
        $this->queueSesResult();

        $this->assertSame([
            'verified' => false,
            'dkim_status' => null,
            'mail_from_domain' => null,
            'mail_from_status' => null,
            'tags' => [],
        ], app(SesSendingDomainsService::class)->status('acme.com'));
    }

    public function test_delete_removes_the_identity(): void
    {
        $this->queueSesResult();

        app(SesSendingDomainsService::class)->delete('acme.com');

        $this->assertSame('acme.com', $this->sesCommandParams('DeleteEmailIdentity')['EmailIdentity']);
    }

    public function test_delete_treats_a_missing_identity_as_success(): void
    {
        $this->queueSesError('NotFoundException');

        app(SesSendingDomainsService::class)->delete('acme.com');

        $this->assertSame(['DeleteEmailIdentity'], $this->sesCommandNames());
    }

    public function test_delete_throws_on_other_errors(): void
    {
        $this->queueSesError('BadRequestException');

        $this->expectException(SendingDomainException::class);

        app(SesSendingDomainsService::class)->delete('acme.com');
    }

    public function test_the_container_builds_the_client_from_the_ses_config(): void
    {
        config(['services.ses' => ['key' => 'k', 'secret' => 's', 'region' => 'eu-west-1']]);
        $this->app->forgetInstance(SesV2Client::class);

        $client = app(SesV2Client::class);

        $this->assertSame('eu-west-1', $client->getRegion());
        $this->assertSame('k', $client->getCredentials()->wait()->getAccessKeyId());
        $httpOptions = $client->getCommand('GetEmailIdentity', ['EmailIdentity' => 'acme.com'])['@http'];
        $this->assertSame(5, $httpOptions['connect_timeout']);
        $this->assertSame(15, $httpOptions['timeout']);
    }
}
