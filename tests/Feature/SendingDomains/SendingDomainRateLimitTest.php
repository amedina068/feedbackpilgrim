<?php

namespace Tests\Feature\SendingDomains;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\MocksSesV2;
use Tests\TestCase;

class SendingDomainRateLimitTest extends TestCase
{
    use ActsAsClientAdmin;
    use MocksSesV2;
    use RefreshDatabase;

    private const LIMIT_MESSAGE = 'Too many attempts. Wait a minute and try again.';

    public function test_the_sixth_connect_request_in_a_minute_is_blocked(): void
    {
        $this->actingAdmin();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postInvalidDomain()->assertSessionDoesntHaveErrors(['domain' => self::LIMIT_MESSAGE]);
        }

        $this->postInvalidDomain()
            ->assertRedirect(route('settings.email'))
            ->assertSessionHasErrors(['domain' => self::LIMIT_MESSAGE]);
    }

    public function test_the_limit_of_one_user_does_not_block_another_user(): void
    {
        $this->actingAdmin();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postInvalidDomain();
        }

        $this->postInvalidDomain()->assertSessionHasErrors(['domain' => self::LIMIT_MESSAGE]);

        $this->actingAdmin();

        $this->postInvalidDomain()->assertSessionDoesntHaveErrors(['domain' => self::LIMIT_MESSAGE]);
    }

    public function test_the_store_route_carries_the_sending_domains_throttle(): void
    {
        $this->assertContains('throttle:sending-domains', Route::getRoutes()->getByName('settings.email.store')->gatherMiddleware());
    }

    public function test_the_limiter_key_is_the_user_id(): void
    {
        [$user] = $this->actingAdmin();
        $request = Request::create('/settings/email', 'POST');
        $request->setUserResolver(fn () => $user);

        $limit = RateLimiter::limiter('sending-domains')($request);

        $this->assertSame('sending-domains:'.$user->id, $limit->key);
    }

    private function postInvalidDomain(): TestResponse
    {
        return $this->from(route('settings.email'))
            ->post(route('settings.email.store'), ['domain' => 'not a domain', 'sender_local_part' => 'feedback']);
    }
}
