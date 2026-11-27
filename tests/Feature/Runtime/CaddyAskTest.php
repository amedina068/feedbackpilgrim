<?php

namespace Tests\Feature\Runtime;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caddy asks this route before it gets an on-demand TLS certificate. The
 * route answers 200 only for a host that the app serves: the app host,
 * APP_DOMAIN and the subdomain of an active project.
 */
class CaddyAskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.host' => 'app.feedback.test', 'app.domain' => 'feedback.test']);
    }

    private function ask(?string $domain)
    {
        $query = $domain === null ? '' : '?domain='.urlencode($domain);

        return $this->get('http://127.0.0.1:8080/caddy/ask'.$query);
    }

    public function test_the_app_host_and_the_app_domain_get_a_certificate(): void
    {
        $this->ask('app.feedback.test')->assertOk();
        $this->ask('feedback.test')->assertOk();
        $this->ask('FEEDBACK.test')->assertOk();
    }

    public function test_the_subdomain_of_an_active_project_gets_a_certificate(): void
    {
        $client = Client::factory()->create(['slug' => 'acme']);
        User::factory()->create()->clients()->attach($client);

        $this->ask('acme.feedback.test')->assertOk();
    }

    public function test_an_unknown_host_gets_no_certificate(): void
    {
        $this->ask('nope.feedback.test')->assertNotFound();
        $this->ask('www.feedback.test')->assertNotFound();
        $this->ask('evil.example.com')->assertNotFound();
        $this->ask('acme.feedback.test.evil.com')->assertNotFound();
        $this->ask('')->assertNotFound();
        $this->ask(null)->assertNotFound();
    }

    public function test_a_project_of_deactivated_users_gets_no_certificate(): void
    {
        $client = Client::factory()->create(['slug' => 'gone']);
        User::factory()->create(['deactivated_at' => now()])->clients()->attach($client);

        $this->ask('gone.feedback.test')->assertNotFound();
    }
}
