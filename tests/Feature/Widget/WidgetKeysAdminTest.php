<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Onboarding, the dashboard snippet, and the widget settings page. The
 * publishable key is public. The identity secret is shown only to the
 * client's own admins.
 */
class WidgetKeysAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Client}
     */
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

    public function test_onboarding_makes_no_sanctum_token_and_no_plain_text_setting(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)
            ->post('/onboarding/project', ['name' => 'Acme', 'subdomain' => 'acme'])
            ->assertRedirect('dashboard');

        $client = Client::where('slug', 'acme')->firstOrFail();

        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertDatabaseMissing('client_settings', ['client_id' => $client->id, 'name' => 'token']);
        $this->assertMatchesRegularExpression('/^pk_[A-Za-z0-9]{32}$/', $client->publishable_key);
        $this->assertNotEmpty($client->identity_secret);
        $this->assertTrue($client->require_identity_verification);
    }

    public function test_publishable_keys_are_unique(): void
    {
        $keys = Client::factory()->count(5)->create()->pluck('publishable_key');

        $this->assertCount(5, $keys->unique());
    }

    public function test_identity_secret_is_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $client = Client::factory()->create();

        $stored = DB::table('clients')->where('id', $client->id)->value('identity_secret');

        $this->assertNotSame($client->identity_secret, $stored);
        $this->assertArrayNotHasKey('identity_secret', $client->toArray());
    }

    public function test_dashboard_snippet_shows_the_publishable_key(): void
    {
        [$user, $client] = $this->actingAdmin();
        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-client=&quot;'.$client->publishable_key.'&quot;', false)
            ->assertDontSee($client->identity_secret);
    }

    public function test_dashboard_renders_without_a_legacy_token_setting(): void
    {
        [$user, $client] = $this->actingAdmin();

        $this->assertSame(0, ClientSetting::where('name', 'token')->count());
        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_widget_settings_page_shows_the_keys(): void
    {
        [$user, $client] = $this->actingAdmin();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/settings/widget')
            ->assertOk()
            ->assertSee($client->publishable_key)
            ->assertSee($client->identity_secret)
            ->assertSee('Require identity verification');
    }

    public function test_widget_settings_page_shows_only_the_current_clients_secret(): void
    {
        [$user, $client] = $this->actingAdmin();
        $otherClient = Client::factory()->create();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/settings/widget')
            ->assertOk()
            ->assertDontSee($otherClient->identity_secret);
    }

    public function test_admin_can_regenerate_the_identity_secret(): void
    {
        [$user, $client] = $this->actingAdmin();
        $oldSecret = $client->identity_secret;

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->post('/settings/widget/identity-secret')
            ->assertRedirect();

        $newSecret = $client->refresh()->identity_secret;
        $this->assertNotEmpty($newSecret);
        $this->assertNotSame($oldSecret, $newSecret);
    }

    public function test_admin_can_turn_identity_verification_on_and_off(): void
    {
        [$user, $client] = $this->actingAdmin();
        $client->forceFill(['require_identity_verification' => false])->save();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch('/settings/widget', ['require_identity_verification' => '1'])
            ->assertRedirect();

        $this->assertTrue($client->refresh()->require_identity_verification);

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch('/settings/widget', [])
            ->assertRedirect();

        $this->assertFalse($client->refresh()->require_identity_verification);
    }

    public function test_guest_cannot_open_widget_settings(): void
    {
        $this->get('/settings/widget')->assertRedirect('/login');
        $this->post('/settings/widget/identity-secret')->assertRedirect('/login');
    }
}
