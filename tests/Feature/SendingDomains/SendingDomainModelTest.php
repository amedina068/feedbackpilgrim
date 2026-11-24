<?php

namespace Tests\Feature\SendingDomains;

use App\Enums\SendingDomainStatus;
use App\Models\Client;
use App\Models\SendingDomain;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendingDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dns_records_list_dkim_mail_from_and_dmarc_records(): void
    {
        config(['services.ses.region' => 'eu-west-1']);

        $sendingDomain = new SendingDomain([
            'domain' => 'acme.com',
            'mail_from_subdomain' => 'bounce.acme.com',
            'dkim_tokens' => ['tok1', 'tok2', 'tok3'],
        ]);

        $this->assertSame([
            ['purpose' => 'DKIM', 'type' => 'CNAME', 'name' => 'tok1._domainkey.acme.com', 'value' => 'tok1.dkim.amazonses.com'],
            ['purpose' => 'DKIM', 'type' => 'CNAME', 'name' => 'tok2._domainkey.acme.com', 'value' => 'tok2.dkim.amazonses.com'],
            ['purpose' => 'DKIM', 'type' => 'CNAME', 'name' => 'tok3._domainkey.acme.com', 'value' => 'tok3.dkim.amazonses.com'],
            ['purpose' => 'MAIL FROM', 'type' => 'MX', 'name' => 'bounce.acme.com', 'value' => '10 feedback-smtp.eu-west-1.amazonses.com'],
            ['purpose' => 'MAIL FROM', 'type' => 'TXT', 'name' => 'bounce.acme.com', 'value' => 'v=spf1 include:amazonses.com ~all'],
            ['purpose' => 'DMARC', 'type' => 'TXT', 'name' => '_dmarc.acme.com', 'value' => 'v=DMARC1; p=none;'],
        ], $sendingDomain->dnsRecords());
    }

    public function test_client_has_one_sending_domain_with_casts(): void
    {
        $client = Client::factory()->create();
        $sendingDomain = SendingDomain::factory()->for($client)->create();

        $this->assertTrue($client->sendingDomain->is($sendingDomain));
        $this->assertTrue($sendingDomain->client->is($client));
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->fresh()->status);
        $this->assertCount(3, $sendingDomain->fresh()->dkim_tokens);
        $this->assertNull($sendingDomain->verified_at);
    }

    public function test_a_client_can_have_only_one_sending_domain(): void
    {
        $client = Client::factory()->create();
        SendingDomain::factory()->for($client)->create();

        $this->expectException(QueryException::class);

        SendingDomain::factory()->for($client)->create();
    }

    public function test_a_domain_is_unique_across_clients(): void
    {
        SendingDomain::factory()->create(['domain' => 'acme.com']);

        $this->expectException(QueryException::class);

        SendingDomain::factory()->create(['domain' => 'acme.com']);
    }

    public function test_display_state_is_verified_for_a_verified_domain(): void
    {
        $this->assertSame('verified', SendingDomain::factory()->verified()->make()->displayState());
    }

    public function test_display_state_is_failed_for_a_failed_domain_even_after_an_earlier_verification(): void
    {
        $this->assertSame('failed', SendingDomain::factory()->failed()->make()->displayState());
        $this->assertSame('failed', SendingDomain::factory()->failed()->make(['verified_at' => now()->subDay()])->displayState());
    }

    public function test_display_state_is_lost_for_a_pending_domain_that_was_verified_before(): void
    {
        $this->assertSame('lost', SendingDomain::factory()->verificationLost()->make()->displayState());
    }

    public function test_display_state_is_pending_for_a_new_domain(): void
    {
        $this->assertSame('pending', SendingDomain::factory()->make()->displayState());
    }
}
