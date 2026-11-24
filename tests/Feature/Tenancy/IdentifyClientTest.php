<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the `identifyClient` + `allowIframe` middleware that resolve the
 * tenant from the request host. The middleware registration moves into
 * bootstrap/app.php on Laravel 11, so these guard that wiring.
 */
class IdentifyClientTest extends TestCase
{
    use RefreshDatabase;

    private function subdomainUrl(string $slug, string $path): string
    {
        return 'http://'.$slug.'.'.config('app.domain').$path;
    }

    public function test_subdomain_resolves_client_for_iframe(): void
    {
        $client = Client::factory()->create();

        $response = $this->get($this->subdomainUrl($client->slug, '/iframe/release-notes'));

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', 'frame-ancestors *;');
    }

    public function test_release_notes_iframe_names_its_own_client(): void
    {
        $client = Client::factory()->create(['name' => 'Acme Rockets']);

        $this->get($this->subdomainUrl($client->slug, '/iframe/release-notes'))
            ->assertOk()
            ->assertSee('client-name="Acme Rockets"', false);
    }

    public function test_unknown_subdomain_is_rejected(): void
    {
        $response = $this->get($this->subdomainUrl('does-not-exist', '/iframe/release-notes'));

        $response->assertNotFound();
    }

    /**
     * Custom domains are not supported. An old `custom_domain` row from a manual insert does not route a host.
     */
    public function test_a_host_outside_the_app_domain_is_rejected(): void
    {
        $client = Client::factory()->create();
        $client->settings()->create([
            'name' => 'custom_domain',
            'value' => 'feedback.acme-example.com',
        ]);

        $this->get('http://feedback.acme-example.com/iframe/release-notes')->assertNotFound();
        $this->assertSame('http://'.$client->slug.'.'.config('app.domain'), $client->public_site);
    }
}
