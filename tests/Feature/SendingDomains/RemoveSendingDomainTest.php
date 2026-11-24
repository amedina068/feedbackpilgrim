<?php

namespace Tests\Feature\SendingDomains;

use App\Actions\SendingDomains\RemoveSendingDomain;
use App\Models\SendingDomain;
use App\Services\SesSendingDomainsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\MocksSesV2;
use Tests\TestCase;

class RemoveSendingDomainTest extends TestCase
{
    use ActsAsClientAdmin;
    use MocksSesV2;
    use RefreshDatabase;

    public function test_an_admin_removes_the_domain_and_the_ses_identity(): void
    {
        config(['mail.from.address' => 'hello@feedbackpilgrim.com']);
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->verified()->for($client)->create(['domain' => 'acme.com']);
        $this->queueSesResult();

        $this->delete(route('settings.email'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHas('success', 'Domain removed. Emails now go out from hello@feedbackpilgrim.com.');

        $this->assertSame(['DeleteEmailIdentity'], $this->sesCommandNames());
        $this->assertSame('acme.com', $this->sesCommandParams('DeleteEmailIdentity')['EmailIdentity']);
        $this->assertFalse(SendingDomain::where('client_id', $client->id)->exists());
    }

    public function test_the_row_is_deleted_before_the_ses_call(): void
    {
        $sendingDomain = SendingDomain::factory()->create(['domain' => 'acme.com']);
        $rowExistedDuringSesCall = null;
        $ses = Mockery::mock(SesSendingDomainsService::class);
        $ses->shouldReceive('delete')->once()->with('acme.com')->andReturnUsing(function () use (&$rowExistedDuringSesCall, $sendingDomain): void {
            $rowExistedDuringSesCall = SendingDomain::whereKey($sendingDomain->id)->exists();
        });

        (new RemoveSendingDomain($ses))($sendingDomain);

        $this->assertFalse($rowExistedDuringSesCall);
    }

    public function test_an_ses_error_is_logged_and_the_row_stays_deleted(): void
    {
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create(['domain' => 'acme.com']);
        $this->queueSesError('LimitExceededException');
        Log::spy();

        $this->delete(route('settings.email'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $this->assertSame(['DeleteEmailIdentity'], $this->sesCommandNames());
        $this->assertFalse(SendingDomain::where('client_id', $client->id)->exists());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ($context['domain'] ?? null) === 'acme.com' && str_contains($message, 'remove'));
    }

    public function test_a_held_lock_keeps_the_row_and_shows_the_safe_message(): void
    {
        Sleep::fake(syncWithCarbon: true);
        [, $client] = $this->actingAdmin();
        SendingDomain::factory()->for($client)->create(['domain' => 'acme.com']);
        $heldLock = Cache::lock('sending-domain:acme.com', 30);
        $this->assertTrue($heldLock->get());

        try {
            $this->from(route('settings.email'))
                ->delete(route('settings.email'))
                ->assertRedirect(route('settings.email'))
                ->assertSessionHasErrors(['domain' => SesSendingDomainsService::SAFE_ERROR_MESSAGE]);
        } finally {
            $heldLock->release();
        }

        $this->assertSame([], $this->sesCommandNames());
        $this->assertTrue(SendingDomain::where('client_id', $client->id)->exists());
    }

    public function test_remove_without_a_domain_redirects_to_the_email_page(): void
    {
        $this->actingAdmin();

        $this->delete(route('settings.email'))
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => 'Connect a domain first.']);

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_the_page_shows_the_remove_form_when_a_domain_exists(): void
    {
        [, $client] = $this->actingAdmin();
        $removeForm = 'action="'.route('settings.email.destroy').'"';

        $this->get(route('settings.email'))->assertDontSee('Remove domain');

        SendingDomain::factory()->for($client)->create();

        $this->get(route('settings.email'))
            ->assertSee($removeForm, false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('Remove domain');
    }
}
