<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The dashboard and the public site must not load in a frame of another
 * site. Only the widget pages in routes/iframe.php allow any parent.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function siteUrl(Client $client, string $path): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').$path;
    }

    private function assertProtectedPage(TestResponse $response): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_the_login_page_cannot_be_framed(): void
    {
        $this->completeInstanceSetup();

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertProtectedPage($response);
    }

    public function test_the_dashboard_cannot_be_framed(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_COMPLETED]);
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'email' => $user->email,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/settings/widget');

        $response->assertOk();
        $this->assertProtectedPage($response);
    }

    public function test_a_redirect_of_the_dashboard_has_the_headers(): void
    {
        $this->completeInstanceSetup();

        $response = $this->get('/dashboard');

        $response->assertRedirect();
        $this->assertProtectedPage($response);
    }

    public function test_the_public_site_cannot_be_framed(): void
    {
        $client = Client::factory()->create();

        $response = $this->get($this->siteUrl($client, '/posts'));

        $response->assertOk();
        $this->assertProtectedPage($response);
    }

    public function test_the_widget_iframes_still_allow_any_parent(): void
    {
        $client = Client::factory()->create();

        foreach (['/iframe/release-notes', '/iframe/roadmap', '/iframe/testimonials'] as $path) {
            $response = $this->get($this->siteUrl($client, $path));

            $response->assertOk();
            $response->assertHeader('Content-Security-Policy', 'frame-ancestors *;');
            $response->assertHeaderMissing('X-Frame-Options');
        }
    }
}
