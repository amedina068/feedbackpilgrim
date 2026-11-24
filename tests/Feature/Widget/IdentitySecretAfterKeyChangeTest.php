<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * After an operator loses APP_KEY and sets a new key, the app cannot
 * decrypt the identity secret of a project. The widget settings page must
 * still open, and the admin must be able to make a new secret.
 */
class IdentitySecretAfterKeyChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['onboarding' => User::ONBOARDING_COMPLETED]);
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['user_id' => $this->user->id, 'client_id' => $this->client->id, 'email' => $this->user->email]);

        $lostKey = new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc');

        DB::table('clients')
            ->where('id', $this->client->id)
            ->update(['identity_secret' => $lostKey->encryptString('secret-of-the-lost-key')]);
    }

    public function test_the_widget_settings_page_opens_and_asks_for_a_new_secret(): void
    {
        $this->actingAs($this->user)
            ->withSession(['fp_client_id' => $this->client->id])
            ->get('/settings/widget')
            ->assertOk()
            ->assertSee('The app cannot read the identity secret');
    }

    public function test_the_admin_can_regenerate_the_secret(): void
    {
        $this->actingAs($this->user)
            ->withSession(['fp_client_id' => $this->client->id])
            ->post('/settings/widget/identity-secret')
            ->assertRedirect();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->client->refresh()->identity_secret);
    }

    public function test_the_widget_refuses_a_user_hash_with_403(): void
    {
        $this->withToken($this->client->publishable_key)
            ->postJson('/api/v1/init', [
                'contact' => ['email' => 'jane@example.com'],
                'user_hash' => str_repeat('a', 64),
            ])
            ->assertForbidden();
    }
}
