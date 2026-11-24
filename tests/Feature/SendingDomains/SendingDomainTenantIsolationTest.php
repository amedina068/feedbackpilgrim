<?php

namespace Tests\Feature\SendingDomains;

use App\Actions\SendingDomains\ConnectSendingDomain;
use App\Models\Client;
use App\Models\SendingDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\MocksSesV2;
use Tests\TestCase;

/**
 * Client B has a failed domain. The tests act as the admin of client A and
 * prove that no write route reads or changes the row or the identity of B.
 */
class SendingDomainTenantIsolationTest extends TestCase
{
    use ActsAsClientAdmin;
    use MocksSesV2;
    use RefreshDatabase;

    private const OTHER_DOMAIN = 'other-client.com';

    private Client $client;

    private SendingDomain $otherSendingDomain;

    /** @var array<string, mixed> */
    private array $otherRowBefore;

    protected function setUp(): void
    {
        parent::setUp();

        [, $otherClient] = $this->actingAdmin();
        $this->otherSendingDomain = SendingDomain::factory()->failed()->for($otherClient)->create(['domain' => self::OTHER_DOMAIN]);
        $this->otherRowBefore = $this->otherSendingDomain->fresh()->getAttributes();

        [, $this->client] = $this->actingAdmin();
    }

    public function test_store_cannot_take_the_domain_of_another_client(): void
    {
        $this->from(route('settings.email'))
            ->post(route('settings.email.store'), ['domain' => self::OTHER_DOMAIN, 'sender_local_part' => 'feedback'])
            ->assertSessionHasErrors(['domain' => ConnectSendingDomain::ALREADY_CONNECTED_MESSAGE]);

        $this->assertSame([], $this->sesCommandNames());
        $this->assertFalse(SendingDomain::where('client_id', $this->client->id)->exists());
        $this->assertOtherClientUntouched();
    }

    public function test_check_reads_only_the_domain_of_the_current_client(): void
    {
        SendingDomain::factory()->for($this->client)->create(['domain' => 'acme.com']);
        $this->queueSesResult([
            'VerifiedForSendingStatus' => true,
            'DkimAttributes' => ['Status' => 'SUCCESS'],
            'MailFromAttributes' => ['MailFromDomain' => 'bounce.acme.com'],
        ]);

        $this->from(route('settings.email'))->post(route('settings.email.check'))->assertSessionHas('success');

        $this->assertSame(['GetEmailIdentity'], $this->sesCommandNames());
        $this->assertOtherClientUntouched();
    }

    public function test_retry_changes_only_the_domain_of_the_current_client(): void
    {
        SendingDomain::factory()->failed()->for($this->client)->create(['domain' => 'acme.com']);
        $this->queueSesResult();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['new1', 'new2', 'new3']]]);
        $this->queueSesResult();

        $this->from(route('settings.email'))->post(route('settings.email.retry'))->assertSessionHas('success');

        $this->assertSame(['DeleteEmailIdentity', 'CreateEmailIdentity', 'PutEmailIdentityMailFromAttributes'], $this->sesCommandNames());
        $this->assertOtherClientUntouched();
    }

    public function test_destroy_removes_only_the_domain_of_the_current_client(): void
    {
        SendingDomain::factory()->for($this->client)->create(['domain' => 'acme.com']);
        $this->queueSesResult();

        $this->delete(route('settings.email'))->assertSessionHas('success');

        $this->assertSame(['DeleteEmailIdentity'], $this->sesCommandNames());
        $this->assertFalse(SendingDomain::where('client_id', $this->client->id)->exists());
        $this->assertOtherClientUntouched();
    }

    public function test_every_write_route_carries_the_sending_domains_throttle(): void
    {
        foreach (['settings.email.store', 'settings.email.check', 'settings.email.retry', 'settings.email.destroy'] as $routeName) {
            $this->assertContains('throttle:sending-domains', Route::getRoutes()->getByName($routeName)->gatherMiddleware(), $routeName);
        }
    }

    private function assertOtherClientUntouched(): void
    {
        $this->assertSame($this->otherRowBefore, $this->otherSendingDomain->fresh()?->getAttributes());

        foreach ($this->sesCommands as $command) {
            $this->assertStringNotContainsString(self::OTHER_DOMAIN, $command->getName().json_encode($command->toArray()));
        }
    }
}
