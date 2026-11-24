<?php

namespace Tests\Feature\SendingDomains;

use App\Actions\SendingDomains\CheckSendingDomain;
use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\MocksSesV2;
use Tests\TestCase;

class CheckSendingDomainTest extends TestCase
{
    use MocksSesV2;
    use RefreshDatabase;

    public function test_a_verified_identity_sets_verified_and_writes_the_status_fields(): void
    {
        $this->freezeSecond();
        $sendingDomain = SendingDomain::factory()->create(['domain' => 'acme.com']);
        $this->queueIdentity(verified: true, dkimStatus: 'SUCCESS', mailFromStatus: 'SUCCESS');

        app(CheckSendingDomain::class)($sendingDomain);

        $sendingDomain->refresh();
        $this->assertSame(['GetEmailIdentity'], $this->sesCommandNames());
        $this->assertSame(SendingDomainStatus::Verified, $sendingDomain->status);
        $this->assertTrue($sendingDomain->verified_at->equalTo(now()));
        $this->assertSame('SUCCESS', $sendingDomain->dkim_status);
        $this->assertSame('SUCCESS', $sendingDomain->mail_from_status);
        $this->assertTrue($sendingDomain->last_checked_at->equalTo(now()));
    }

    public function test_a_second_verified_check_keeps_the_first_verified_at(): void
    {
        $firstVerifiedAt = Carbon::parse('2026-01-01 10:00:00');
        $sendingDomain = SendingDomain::factory()->verified()->create(['verified_at' => $firstVerifiedAt]);
        $this->queueIdentity(verified: true, dkimStatus: 'SUCCESS', mailFromStatus: 'SUCCESS');

        app(CheckSendingDomain::class)($sendingDomain);

        $this->assertTrue($sendingDomain->refresh()->verified_at->equalTo($firstVerifiedAt));
    }

    public function test_a_failed_dkim_status_sets_failed(): void
    {
        $sendingDomain = SendingDomain::factory()->create();
        $this->queueIdentity(verified: false, dkimStatus: 'FAILED', mailFromStatus: 'PENDING');

        app(CheckSendingDomain::class)($sendingDomain);

        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Failed, $sendingDomain->status);
        $this->assertSame('FAILED', $sendingDomain->dkim_status);
        $this->assertNull($sendingDomain->verified_at);
        $this->assertNotNull($sendingDomain->last_checked_at);
    }

    public function test_any_other_result_keeps_pending(): void
    {
        $sendingDomain = SendingDomain::factory()->create();
        $this->queueIdentity(verified: false, dkimStatus: 'PENDING', mailFromStatus: 'PENDING');

        app(CheckSendingDomain::class)($sendingDomain);

        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
        $this->assertSame('PENDING', $sendingDomain->dkim_status);
        $this->assertSame('PENDING', $sendingDomain->mail_from_status);
        $this->assertNotNull($sendingDomain->last_checked_at);
    }

    public function test_a_verified_domain_that_ses_no_longer_verifies_goes_to_pending_and_keeps_verified_at(): void
    {
        $verifiedAt = Carbon::parse('2026-01-01 10:00:00');
        $sendingDomain = SendingDomain::factory()->verified()->create(['verified_at' => $verifiedAt]);
        $this->queueIdentity(verified: false, dkimStatus: 'TEMPORARY_FAILURE', mailFromStatus: 'SUCCESS');

        app(CheckSendingDomain::class)($sendingDomain);

        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
        $this->assertTrue($sendingDomain->verified_at->equalTo($verifiedAt));
    }

    public function test_a_missing_mail_from_domain_is_set_again(): void
    {
        $sendingDomain = SendingDomain::factory()->create(['domain' => 'acme.com', 'mail_from_subdomain' => 'bounce.acme.com']);
        $this->queueSesResult([
            'VerifiedForSendingStatus' => false,
            'DkimAttributes' => ['Status' => 'PENDING'],
        ]);
        $this->queueSesResult();

        app(CheckSendingDomain::class)($sendingDomain);

        $this->assertSame(['GetEmailIdentity', 'PutEmailIdentityMailFromAttributes'], $this->sesCommandNames());
        $this->assertSame('bounce.acme.com', $this->sesCommandParams('PutEmailIdentityMailFromAttributes')['MailFromDomain']);
        $this->assertNotNull($sendingDomain->refresh()->last_checked_at);
    }

    public function test_a_failed_mail_from_retry_is_logged_and_the_status_is_still_saved(): void
    {
        $sendingDomain = SendingDomain::factory()->create(['domain' => 'acme.com']);
        $this->queueSesResult([
            'VerifiedForSendingStatus' => true,
            'DkimAttributes' => ['Status' => 'SUCCESS'],
        ]);
        $this->queueSesError('BadRequestException');
        Log::spy();

        app(CheckSendingDomain::class)($sendingDomain);

        $sendingDomain->refresh();
        $this->assertSame(['GetEmailIdentity', 'PutEmailIdentityMailFromAttributes'], $this->sesCommandNames());
        $this->assertSame(SendingDomainStatus::Verified, $sendingDomain->status);
        $this->assertSame('SUCCESS', $sendingDomain->dkim_status);
        $this->assertNotNull($sendingDomain->last_checked_at);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ($context['domain'] ?? null) === 'acme.com' && $message === 'MAIL FROM setup failed. The next check applies it again.');
    }

    public function test_an_identity_that_no_longer_exists_sets_failed(): void
    {
        $sendingDomain = SendingDomain::factory()->create();
        $this->queueSesError('NotFoundException');

        app(CheckSendingDomain::class)($sendingDomain);

        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Failed, $sendingDomain->status);
        $this->assertNotNull($sendingDomain->last_checked_at);
    }

    public function test_any_other_ses_error_propagates_and_leaves_the_row_unchanged(): void
    {
        $sendingDomain = SendingDomain::factory()->create();
        $this->queueSesError('BadRequestException');

        try {
            app(CheckSendingDomain::class)($sendingDomain);
            $this->fail('Expected a SendingDomainException.');
        } catch (SendingDomainException) {
            $sendingDomain->refresh();
            $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
            $this->assertNull($sendingDomain->last_checked_at);
        }
    }

    private function queueIdentity(bool $verified, string $dkimStatus, string $mailFromStatus): void
    {
        $this->queueSesResult([
            'VerifiedForSendingStatus' => $verified,
            'DkimAttributes' => ['Status' => $dkimStatus],
            'MailFromAttributes' => ['MailFromDomain' => 'bounce.acme.com', 'MailFromDomainStatus' => $mailFromStatus],
        ]);
    }
}
