<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        $user = User::factory()->create([
            'onboarding' => User::ONBOARDING_COMPLETED,
        ]);
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        // The app layout's view composer resolves a contact-auth URL for the client.
        Contact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'email' => $user->email,
        ]);

        return [$user, $client];
    }

    public function test_settings_index_redirects_to_api_tokens(): void
    {
        [$user, $client] = $this->actingAdmin();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/settings')
            ->assertRedirect(route('settings.api-tokens'));
    }

    public function test_api_tokens_page_renders_both_sections(): void
    {
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/settings/api-tokens');

        $response->assertOk();
        $response->assertSee('API token generation');
        $response->assertSee('MCP');
        $response->assertSee('mcp-setup', false); // the Vue component mount point
        $response->assertSee(rtrim((string) config('app.url'), '/').'/mcp');
        $response->assertSee(route('documentation.postman.download'));
    }

    public function test_testimonials_page_renders(): void
    {
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/settings/testimonials');

        $response->assertOk();
        $response->assertSee('Testimonials');
        $response->assertSee('Request email body');
    }
}
