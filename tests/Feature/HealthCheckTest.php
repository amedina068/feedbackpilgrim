<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Docker health check requests /up on the container itself, so the host
 * is not APP_DOMAIN and not a client subdomain.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_answers_on_the_app_domain(): void
    {
        $this->get('http://'.config('app.domain').'/up')->assertOk();
    }

    public function test_up_answers_on_any_host(): void
    {
        $this->get('http://127.0.0.1:8080/up')->assertOk();
    }
}
