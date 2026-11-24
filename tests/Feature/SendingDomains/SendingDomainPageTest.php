<?php

namespace Tests\Feature\SendingDomains;

use App\Enums\SendingDomainStatus;
use App\Models\Client;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\MocksSesV2;
use Tests\TestCase;

class SendingDomainPageTest extends TestCase
{
    use ActsAsClientAdmin;
    use MocksSesV2;
    use RefreshDatabase;

    public function test_the_page_shows_the_connect_form_when_no_domain_exists(): void
    {
        $this->actingAdmin();

        $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee('Connect a domain')
            ->assertSee('action="'.route('settings.email.store').'"', false)
            ->assertSee('name="domain"', false)
            ->assertSee('name="sender_local_part"', false);
    }

    public function test_the_settings_navigation_links_to_the_email_page(): void
    {
        $this->actingAdmin();

        $this->get(route('settings.api-tokens'))
            ->assertOk()
            ->assertSee(route('settings.email'));
    }

    public function test_an_admin_connects_a_domain_and_sees_the_dns_records(): void
    {
        [, $client] = $this->actingAdmin();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['tok1', 'tok2', 'tok3']]]);
        $this->queueSesResult();

        $this->post(route('settings.email.store'), ['domain' => ' Acme.com ', 'sender_local_part' => 'Feedback'])
            ->assertRedirect(route('settings.email'))
            ->assertSessionHas('success');

        $sendingDomain = SendingDomain::where('client_id', $client->id)->sole();
        $this->assertSame('acme.com', $sendingDomain->domain);
        $this->assertSame('feedback', $sendingDomain->sender_local_part);

        $response = $this->get(route('settings.email'))->assertOk()->assertDontSee('name="domain"', false);

        foreach ($sendingDomain->dnsRecords() as $record) {
            $response->assertSee($record['name'])->assertSee($record['value']);
        }
    }

    public function test_an_ses_error_shows_the_safe_message(): void
    {
        [, $client] = $this->actingAdmin();
        $this->queueSesError('LimitExceededException', 'raw aws detail');

        $this->from(route('settings.email'))
            ->post(route('settings.email.store'), ['domain' => 'acme.com', 'sender_local_part' => 'feedback'])
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => SesSendingDomainsService::SAFE_ERROR_MESSAGE]);

        $this->assertFalse(SendingDomain::where('client_id', $client->id)->exists());
        $this->get(route('settings.email'))
            ->assertSee(SesSendingDomainsService::SAFE_ERROR_MESSAGE)
            ->assertDontSee('raw aws detail');
    }

    public function test_an_invalid_domain_is_rejected_before_any_ses_call(): void
    {
        $this->actingAdmin();

        $this->post(route('settings.email.store'), ['domain' => 'not a domain', 'sender_local_part' => 'bad address!'])
            ->assertSessionHasErrors(['domain', 'sender_local_part']);

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_a_second_domain_is_refused_without_calling_ses(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create(['domain' => 'first.com']);

        $this->post(route('settings.email.store'), ['domain' => 'second.com', 'sender_local_part' => 'feedback'])
            ->assertSessionHasErrors('domain');

        $this->assertSame([], $this->sesCommandNames());
        $this->assertSame(['first.com'], SendingDomain::where('client_id', $client->id)->pluck('domain')->all());
    }

    public function test_check_without_a_domain_redirects_with_an_error_and_calls_no_ses(): void
    {
        $this->actingAdmin();
        SendingDomain::factory()->for(Client::factory())->create();

        $this->post(route('settings.email.check'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => 'Connect a domain first.']);

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_check_now_verifies_the_domain_and_says_so(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->for($client)->create(['domain' => 'acme.com', 'sender_local_part' => 'feedback']);
        $this->queueIdentity(verified: true, dkimStatus: 'SUCCESS');

        $this->from(route('settings.email'))
            ->post(route('settings.email.check'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHas('success', 'Verified. Emails now go out from feedback@acme.com.');

        $this->assertSame(SendingDomainStatus::Verified, $sendingDomain->refresh()->status);
    }

    public function test_check_now_says_when_the_domain_is_still_pending(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create();
        $this->queueIdentity(verified: false, dkimStatus: 'PENDING');

        $this->from(route('settings.email'))
            ->post(route('settings.email.check'))
            ->assertSessionHas('success', 'Still waiting for the DNS records.');
    }

    public function test_check_now_says_when_the_domain_failed(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create();
        $this->queueIdentity(verified: false, dkimStatus: 'FAILED');

        $this->from(route('settings.email'))
            ->post(route('settings.email.check'))
            ->assertSessionHas('success', 'SES could not verify this domain.');
    }

    public function test_check_now_shows_the_safe_message_on_an_ses_error(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create();
        $this->queueSesError('BadRequestException', 'raw aws detail');

        $this->from(route('settings.email'))
            ->post(route('settings.email.check'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => SesSendingDomainsService::SAFE_ERROR_MESSAGE]);
    }

    public function test_the_check_route_is_throttled(): void
    {
        $this->assertContains('throttle:sending-domains', Route::getRoutes()->getByName('settings.email.check')->gatherMiddleware());
    }

    public function test_the_page_shows_check_now_and_the_time_of_the_last_check(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create(['last_checked_at' => now()->subMinutes(5)]);

        $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee('action="'.route('settings.email.check').'"', false)
            ->assertSee('Check now')
            ->assertSee('Last checked 5 minutes ago');
    }

    public function test_the_no_domain_state_shows_the_fallback_address_and_the_from_line_preview(): void
    {
        [, $client] = $this->actingAdmin();
        $fallback = config('mail.from.address');

        $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee("Until you connect a domain, emails come from {$fallback} with your name. Use a sender address that someone reads, because replies go there.")
            ->assertSee("{$client->name} <feedback@example.com>")
            ->assertSee('Connect domain')
            ->assertDontSee('Check now')
            ->assertDontSee('Try again')
            ->assertDontSee('Remove domain');
    }

    public function test_the_pending_state_shows_the_dns_records_check_now_and_remove(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->for($client)->create();

        $response = $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee('>Pending</span>', false)
            ->assertSee('Some DNS providers add your domain to the name for you. If yours does, enter only the part before your domain, for example')
            ->assertSee('abc123._domainkey')
            ->assertSee('Gmail and Yahoo require DMARC for bulk senders.')
            ->assertSee('aria-label="Copy DKIM record 1 name"', false)
            ->assertSee('aria-label="Copy DMARC TXT record value"', false)
            ->assertSee('break-all font-mono', false)
            ->assertSee('Check now')
            ->assertSee('Remove domain')
            ->assertSee('Remove this domain? Emails will go out from '.config('mail.from.address').' until you connect a new one.')
            ->assertDontSee('Try again');

        foreach ($sendingDomain->dnsRecords() as $record) {
            $response->assertSee($record['name'])->assertSee($record['value']);
        }

        $dnsRecords = view('settings.partials.sending-domain-dns-records', ['sendingDomain' => $sendingDomain])->render();
        $this->assertStringNotContainsString('truncate', $dnsRecords);
    }

    public function test_the_verified_state_shows_the_from_line_and_hides_the_dns_records_behind_a_toggle(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->verified()->for($client)->create(['domain' => 'acme.com']);

        $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee('>Verified</span>', false)
            ->assertSee("{$client->name} <feedback@acme.com>")
            ->assertSee('Show DNS records')
            ->assertDontSee('<details open', false)
            ->assertSee($sendingDomain->dnsRecords()[0]['name'])
            ->assertSee('Remove domain')
            ->assertDontSee('Check now')
            ->assertDontSee('Try again');
    }

    public function test_the_verification_lost_state_shows_the_warning_check_now_and_remove(): void
    {
        [, $client] = $this->actingAdmin();
        $sendingDomain = SendingDomain::factory()->verificationLost()->for($client)->create();

        $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee('>Verification lost</span>', false)
            ->assertSee('This domain was verified, but SES can no longer confirm it. Emails are sending from '.config('mail.from.address').' until this is fixed.')
            ->assertSee($sendingDomain->dnsRecords()[0]['name'])
            ->assertSee('break-all font-mono', false)
            ->assertSee('Check now')
            ->assertSee('Remove domain')
            ->assertDontSee('Try again');
    }

    public function test_the_failed_state_shows_the_reason_try_again_and_remove(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->failed()->for($client)->create();

        $this->get(route('settings.email'))
            ->assertOk()
            ->assertSee('>Failed</span>', false)
            ->assertSee('SES looked for the DKIM records for 72 hours and did not find them.')
            ->assertSee('Emails are sending from '.config('mail.from.address').' until this is fixed.')
            ->assertSee('Try again creates new records. Replace the old CNAME records with the new ones.')
            ->assertSee('Try again')
            ->assertSee('Remove domain')
            ->assertDontSee('Check now');
    }

    public function test_the_from_preview_does_not_let_vue_compile_old_input(): void
    {
        $this->actingAdmin();

        $response = $this->from(route('settings.email'))
            ->followingRedirects()
            ->post(route('settings.email.store'), ['domain' => 'evil{{1+1}}', 'sender_local_part' => 'feedback'])
            ->assertOk()
            ->assertSee('Enter a domain like example.com.')
            ->assertSee('<output', false);

        $this->assertMatchesRegularExpression('/<output\\b[^>]*\\bv-pre\\b[^>]*>[^<]*evil\\{\\{1\\+1\\}\\}/', $response->getContent());
    }

    public function test_lost_verification_means_verified_before_but_not_now(): void
    {
        $this->assertTrue(SendingDomain::factory()->verificationLost()->make()->lostVerification());
        $this->assertFalse(SendingDomain::factory()->verified()->make()->lostVerification());
        $this->assertFalse(SendingDomain::factory()->make()->lostVerification());
    }

    private function queueIdentity(bool $verified, string $dkimStatus): void
    {
        $this->queueSesResult([
            'VerifiedForSendingStatus' => $verified,
            'DkimAttributes' => ['Status' => $dkimStatus],
            'MailFromAttributes' => ['MailFromDomain' => 'bounce.acme.com', 'MailFromDomainStatus' => 'SUCCESS'],
        ]);
    }
}
