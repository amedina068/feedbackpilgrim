<?php

namespace Tests\Feature\SendingDomains;

use App\Actions\SendingDomains\ConnectSendingDomain;
use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\Client;
use App\Models\SendingDomain;
use Aws\Result;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\MocksSesV2;
use Tests\TestCase;

class ConnectSendingDomainTest extends TestCase
{
    use MocksSesV2;
    use RefreshDatabase;

    public function test_it_creates_the_identity_sets_mail_from_and_saves_a_pending_row(): void
    {
        $client = Client::factory()->create();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesResult();

        $sendingDomain = app(ConnectSendingDomain::class)($client->id, 'acme.com', 'feedback');

        $this->assertSame(['CreateEmailIdentity', 'PutEmailIdentityMailFromAttributes'], $this->sesCommandNames());
        $this->assertSame('bounce.acme.com', $this->sesCommandParams('PutEmailIdentityMailFromAttributes')['MailFromDomain']);

        $sendingDomain->refresh();
        $this->assertSame($client->id, $sendingDomain->client_id);
        $this->assertSame('acme.com', $sendingDomain->domain);
        $this->assertSame('feedback', $sendingDomain->sender_local_part);
        $this->assertSame('bounce.acme.com', $sendingDomain->mail_from_subdomain);
        $this->assertSame(['t1', 't2', 't3'], $sendingDomain->dkim_tokens);
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
        $this->assertNull($sendingDomain->verified_at);
    }

    public function test_a_domain_that_another_client_has_is_rejected_without_any_ses_call(): void
    {
        SendingDomain::factory()->create(['domain' => 'acme.com']);
        $client = Client::factory()->create();

        $this->assertConnectFails($client->id, 'This domain is already connected.');

        $this->assertSame([], $this->sesCommandNames());
        $this->assertFalse(SendingDomain::where('client_id', $client->id)->exists());
    }

    public function test_a_held_lock_for_the_domain_is_rejected_without_any_ses_call(): void
    {
        Sleep::fake(syncWithCarbon: true);
        $client = Client::factory()->create();
        $heldLock = Cache::lock('sending-domain:acme.com', 30);
        $this->assertTrue($heldLock->get());

        try {
            $this->assertConnectFails($client->id, 'This domain is already connected.');
        } finally {
            $heldLock->release();
        }

        $this->assertSame([], $this->sesCommandNames());
        $this->assertSame(0, SendingDomain::count());
    }

    public function test_an_orphan_identity_with_our_tag_is_deleted_and_created_again_with_new_tokens(): void
    {
        $client = Client::factory()->create();
        $this->queueSesError('AlreadyExistsException');
        $this->queueSesResult(['Tags' => [['Key' => 'app', 'Value' => 'feedbackpilgrim']]]);
        $this->queueSesResult();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['new1', 'new2', 'new3']]]);
        $this->queueSesResult();

        $sendingDomain = app(ConnectSendingDomain::class)($client->id, 'acme.com', 'feedback');

        $this->assertSame([
            'CreateEmailIdentity',
            'GetEmailIdentity',
            'DeleteEmailIdentity',
            'CreateEmailIdentity',
            'PutEmailIdentityMailFromAttributes',
        ], $this->sesCommandNames());
        $this->assertSame(['new1', 'new2', 'new3'], $sendingDomain->refresh()->dkim_tokens);
    }

    public function test_an_identity_without_our_tag_is_rejected_and_never_deleted(): void
    {
        $client = Client::factory()->create();
        $this->queueSesError('AlreadyExistsException');
        $this->queueSesResult(['Tags' => [['Key' => 'owner', 'Value' => 'someone-else']]]);

        $this->assertConnectFails($client->id, 'This domain is already connected.');

        $this->assertSame(['CreateEmailIdentity', 'GetEmailIdentity'], $this->sesCommandNames());
        $this->assertNotContains('DeleteEmailIdentity', $this->sesCommandNames());
        $this->assertSame(0, SendingDomain::count());
    }

    public function test_a_mail_from_failure_is_logged_and_the_row_is_still_saved(): void
    {
        $client = Client::factory()->create();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesError('BadRequestException');
        Log::spy();

        $sendingDomain = app(ConnectSendingDomain::class)($client->id, 'acme.com', 'feedback');

        $sendingDomain->refresh();
        $this->assertSame(SendingDomainStatus::Pending, $sendingDomain->status);
        $this->assertNull($sendingDomain->mail_from_status);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ($context['domain'] ?? null) === 'acme.com' && $message === 'MAIL FROM setup failed. The next check applies it again.');
    }

    public function test_a_concurrent_insert_rolls_back_the_identity_and_keeps_one_row(): void
    {
        $client = Client::factory()->create();
        SendingDomain::factory()->for($client)->create(['domain' => 'first.com']);
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesResult();
        $this->queueSesResult();

        $this->assertConnectFails($client->id, 'This client already has a connected domain.');

        $this->assertSame('DeleteEmailIdentity', $this->sesCommandNames()[2]);
        $this->assertSame('acme.com', $this->sesCommandParams('DeleteEmailIdentity')['EmailIdentity']);
        $this->assertSame(['first.com'], SendingDomain::where('client_id', $client->id)->pluck('domain')->all());
    }

    public function test_any_other_insert_error_rolls_back_the_identity_and_propagates(): void
    {
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesResult();
        $this->queueSesResult();

        try {
            app(ConnectSendingDomain::class)(999999, 'acme.com', 'feedback');
            $this->fail('Expected a QueryException.');
        } catch (QueryException) {
            $this->assertContains('DeleteEmailIdentity', $this->sesCommandNames());
        }

        $this->assertSame(0, SendingDomain::count());
    }

    public function test_a_domain_taken_after_the_first_check_rolls_back_and_says_the_domain_is_connected(): void
    {
        $client = Client::factory()->create();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->sesMock->append(function (): Result {
            SendingDomain::factory()->create(['domain' => 'acme.com']);

            return new Result;
        });
        $this->queueSesResult();

        $this->assertConnectFails($client->id, ConnectSendingDomain::ALREADY_CONNECTED_MESSAGE);

        $this->assertContains('DeleteEmailIdentity', $this->sesCommandNames());
        $this->assertFalse(SendingDomain::where('client_id', $client->id)->exists());
    }

    public function test_a_failed_rollback_is_logged_and_the_insert_error_is_still_thrown(): void
    {
        $client = Client::factory()->create();
        SendingDomain::factory()->for($client)->create(['domain' => 'first.com']);
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesResult();
        $this->queueSesError('BadRequestException');
        Log::spy();

        $this->assertConnectFails($client->id, 'This client already has a connected domain.');

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ($context['domain'] ?? null) === 'acme.com' && str_contains($message, 'roll back'));
    }

    public function test_a_failed_rollback_still_propagates_any_other_insert_error(): void
    {
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesResult();
        $this->queueSesError('BadRequestException');

        $this->expectException(QueryException::class);

        app(ConnectSendingDomain::class)(999999, 'acme.com', 'feedback');
    }

    public function test_the_domain_lock_lasts_60_seconds(): void
    {
        $client = Client::factory()->create();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->sesMock->append(function (): Result {
            $this->travel(59)->seconds();
            $this->assertFalse(Cache::lock('sending-domain:acme.com', 1)->get());

            return new Result;
        });

        app(ConnectSendingDomain::class)($client->id, 'acme.com', 'feedback');

        $this->assertSame(1, SendingDomain::count());
    }

    private function assertConnectFails(int $clientId, string $message): void
    {
        try {
            app(ConnectSendingDomain::class)($clientId, 'acme.com', 'feedback');
            $this->fail('Expected a SendingDomainException.');
        } catch (SendingDomainException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
