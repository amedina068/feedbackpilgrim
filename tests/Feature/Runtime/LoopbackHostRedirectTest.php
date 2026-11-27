<?php

namespace Tests\Feature\Runtime;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin routes bind to the host of APP_URL. A person who opens
 * 127.0.0.1 or [::1] goes to the same page on the APP_URL host, with the
 * same port, and does not get a 404.
 */
class LoopbackHostRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_ipv4_loopback_request_goes_to_the_app_host(): void
    {
        $this->get('http://127.0.0.1:18080/setup?token=abc')
            ->assertRedirect('http://localhost:18080/setup?token=abc');
    }

    public function test_an_ipv6_loopback_request_goes_to_the_app_host(): void
    {
        $this->get('http://[::1]:8080/login')
            ->assertRedirect('http://localhost:8080/login');
    }

    public function test_the_health_check_answers_on_the_loopback_host(): void
    {
        $this->get('http://127.0.0.1:8080/up')->assertOk();
    }

    public function test_a_post_is_not_redirected(): void
    {
        $this->post('http://127.0.0.1:8080/login')->assertStatus(404);
    }

    public function test_no_redirect_when_the_app_host_is_the_loopback_address(): void
    {
        config(['app.host' => '127.0.0.1']);

        $this->get('http://127.0.0.1:8080/nothing-here')->assertNotFound();
    }
}
