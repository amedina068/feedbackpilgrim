<?php

namespace Tests\Feature\SendingDomains;

use App\Actions\SendingDomains\RetrySendingDomain;
use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\MocksSesV2;
use Tests\TestCase;

class RetrySendingDomainTest extends TestCase
{
    use ActsAsClientAdmin;
    use MocksSesV2;
    use RefreshDatabase;

    public function test_a_failed_domain_gets_a_new_identity_and_goes_back_to_pending(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->failed()->for($client)->create([
            'domain' => 'acme.com',
            'mail_from_subdomain' => 'bounce.acme.com',
            'mail_from_status' => 'FAILED',
            'verified_at' => now()->subDay(),
        ]);
        $this->queueSesResult();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['new1', 'new2', 'new3']]]);
        $this->queueSesResult();

        $this->from(route('settings.email'))
            ->post(route('settings.email.retry'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHas('success', 'New DNS records are ready. Replace the old CNAME records with these.');

        $this->assertSame(['DeleteEmailIdentity', 'CreateEmailIdentity', 'PutEmailIdentityMailFromAttributes'], $this->sesCommandNames());
        $this->assertSame('acme.com', $this->sesCommandParams('DeleteEmailIdentity')['EmailIdentity']);
        $this->assertSame('acme.com', $this->sesCommandParams('CreateEmailIdentity')['EmailIdentity']);
        $this->assertSame('bounce.acme.com', $this->sesCommandParams('PutEmailIdentityMailFromAttributes')['MailFromDomain']);

        $sendingDomain->refresh();
        $this->assertSame(['new1', 'new2', 'new3'], $sendingDomain->dkim_tokens);
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
        $this->assertNull($sendingDomain->verified_at);
        $this->assertNull($sendingDomain->dkim_status);
        $this->assertNull($sendingDomain->mail_from_status);
        $this->assertNull($sendingDomain->last_checked_at);
        $this->assertSame(1, SendingDomain::count());
    }

    public function test_a_failed_mail_from_step_is_logged_and_the_retry_still_succeeds(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->failed()->for($client)->create(['domain' => 'acme.com']);
        $this->queueSesResult();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['new1', 'new2', 'new3']]]);
        $this->queueSesError('BadRequestException');
        Log::spy();

        $this->from(route('settings.email'))
            ->post(route('settings.email.retry'))
            ->assertSessionHas('success');

        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
        $this->assertSame(['new1', 'new2', 'new3'], $sendingDomain->dkim_tokens);
        $this->assertNull($sendingDomain->mail_from_status);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ($context['domain'] ?? null) === 'acme.com' && $message === 'MAIL FROM setup failed. The next check applies it again.');
    }

    public function test_an_ses_error_shows_the_safe_message_and_leaves_the_row_failed(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->failed()->for($client)->create();
        $tokens = $sendingDomain->dkim_tokens;
        $this->queueSesResult();
        $this->queueSesError('LimitExceededException', 'raw aws detail');

        $this->from(route('settings.email'))
            ->post(route('settings.email.retry'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => SesSendingDomainsService::SAFE_ERROR_MESSAGE]);

        $this->assertSame(['DeleteEmailIdentity', 'CreateEmailIdentity'], $this->sesCommandNames());
        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Failed, $sendingDomain->status);
        $this->assertSame($tokens, $sendingDomain->dkim_tokens);
    }

    public function test_a_held_lock_keeps_the_row_failed_and_shows_the_safe_message(): void
    {
        Sleep::fake(syncWithCarbon: true);
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->failed()->for($client)->create(['domain' => 'acme.com']);
        $tokens = $sendingDomain->dkim_tokens;
        $heldLock = Cache::lock('sending-domain:acme.com', 30);
        $this->assertTrue($heldLock->get());

        try {
            $this->from(route('settings.email'))
                ->post(route('settings.email.retry'))
                ->assertRedirect(route('settings.email'))
                ->assertSessionHasErrors(['domain' => SesSendingDomainsService::SAFE_ERROR_MESSAGE]);
        } finally {
            $heldLock->release();
        }

        $this->assertSame([], $this->sesCommandNames());
        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Failed, $sendingDomain->status);
        $this->assertSame($tokens, $sendingDomain->dkim_tokens);
    }

    public function test_a_domain_removed_before_the_retry_stops_without_any_ses_call(): void
    {
        $sendingDomain = SendingDomain::factory()->failed()->create();
        SendingDomain::whereKey($sendingDomain->id)->delete();

        try {
            app(RetrySendingDomain::class)($sendingDomain);
            $this->fail('Expected a SendingDomainException.');
        } catch (SendingDomainException $exception) {
            $this->assertSame('This domain was removed.', $exception->getMessage());
        }

        $this->assertSame([], $this->sesCommandNames());
        $this->assertSame(0, SendingDomain::count());
    }

    public function test_a_verified_domain_cannot_be_retried(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->verified()->for($client)->create();

        $this->from(route('settings.email'))
            ->post(route('settings.email.retry'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => 'Only a failed domain can be retried.']);

        $this->assertSame([], $this->sesCommandNames());
        $this->assertSame(SendingDomainStatus::Verified, $sendingDomain->refresh()->status);
    }

    public function test_a_pending_domain_cannot_be_retried(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create();

        $this->from(route('settings.email'))
            ->post(route('settings.email.retry'))
            ->assertSessionHasErrors(['domain' => 'Only a failed domain can be retried.']);

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_retry_without_a_domain_redirects_to_the_email_page(): void
    {
        $this->actingAdmin();

        $this->post(route('settings.email.retry'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => 'Connect a domain first.']);

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_the_page_shows_try_again_only_for_a_failed_domain(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->failed()->for($client)->create();
        $retryAction = 'action="'.route('settings.email.retry').'"';

        $this->get(route('settings.email'))->assertSee($retryAction, false)->assertSee('Try again');

        $sendingDomain->update(['status' => SendingDomainStatus::Pending]);

        $this->get(route('settings.email'))->assertDontSee($retryAction, false);
    }
}
