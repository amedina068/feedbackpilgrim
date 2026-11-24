<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Before this fix, onboarding made a Sanctum token with all abilities and
 * stored its plain text in a "token" client setting. Host apps put that
 * value in a public script tag. The migration revokes those tokens.
 */
class RevokePublicWidgetTokensMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_15_000002_revoke_public_widget_tokens.php';

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);

        // Simulate a client that existed before publishable keys.
        Client::query()->whereKey($this->client->id)->update([
            'publishable_key' => null,
            'identity_secret' => null,
            'require_identity_verification' => false,
        ]);
    }

    /**
     * Reproduce what OnboardingClientController did before the fix.
     */
    private function makeLegacyPublicToken(): string
    {
        $token = $this->user->createToken('public_token');
        $token->accessToken->forceFill(['client_id' => $this->client->id])->save();

        ClientSetting::create([
            'client_id' => $this->client->id,
            'name' => 'token',
            'value' => $token->plainTextToken,
        ]);

        return $token->plainTextToken;
    }

    private function makePrivateToken(): string
    {
        $token = $this->user->createToken('API Key', ['*']);
        $token->accessToken->forceFill(['client_id' => $this->client->id])->save();

        return $token->plainTextToken;
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->up();
    }

    private function mcpRequest(string $token)
    {
        return $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], ['Accept' => 'application/json']);
    }

    public function test_old_public_token_has_full_api_access_before_the_migration(): void
    {
        $publicToken = $this->makeLegacyPublicToken();

        $this->withToken($publicToken)->getJson('/api/public/v1/contacts')->assertOk();
    }

    public function test_migration_revokes_the_token_and_deletes_the_plain_text_setting(): void
    {
        $publicToken = $this->makeLegacyPublicToken();

        $this->runMigration();

        $this->assertNull(PersonalAccessToken::findToken($publicToken));
        $this->assertDatabaseMissing('client_settings', [
            'client_id' => $this->client->id,
            'name' => 'token',
        ]);
        $this->assertDatabaseMissing('client_settings', ['value' => $publicToken]);
    }

    public function test_migration_gives_existing_clients_keys_and_leaves_verification_off(): void
    {
        $this->makeLegacyPublicToken();

        $this->runMigration();

        $this->client->refresh();
        $this->assertMatchesRegularExpression('/^pk_[A-Za-z0-9]{32}$/', $this->client->publishable_key);
        $this->assertNotEmpty($this->client->identity_secret);
        $this->assertFalse($this->client->require_identity_verification);
    }

    public function test_migration_keeps_existing_publishable_keys(): void
    {
        Client::query()->whereKey($this->client->id)->update(['publishable_key' => 'pk_'.str_repeat('b', 32)]);

        $this->runMigration();

        $this->assertSame('pk_'.str_repeat('b', 32), $this->client->refresh()->publishable_key);
    }

    public function test_old_public_token_gets_401_on_the_public_api_after_the_migration(): void
    {
        $publicToken = $this->makeLegacyPublicToken();

        $this->runMigration();

        $this->withToken($publicToken)->getJson('/api/public/v1/contacts')->assertStatus(401);
        $this->withToken($publicToken)->postJson('/api/public/v1/init', [
            'contact' => ['email' => 'victim@example.com'],
        ])->assertStatus(401);
    }

    public function test_old_public_token_gets_401_on_mcp_after_the_migration(): void
    {
        $publicToken = $this->makeLegacyPublicToken();

        $this->runMigration();

        $this->mcpRequest($publicToken)->assertStatus(401);
    }

    public function test_private_token_still_works_after_the_migration(): void
    {
        $this->makeLegacyPublicToken();
        $privateToken = $this->makePrivateToken();

        $this->runMigration();

        $this->withToken($privateToken)->getJson('/api/public/v1/contacts')->assertOk();
        $this->mcpRequest($privateToken)->assertOk();
    }

    public function test_widget_rejects_the_old_value_after_the_migration(): void
    {
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('set')->andReturn(true);

        $publicToken = $this->makeLegacyPublicToken();

        $this->runMigration();

        $this->withToken($publicToken)->postJson('/api/v1/init', [
            'contact' => ['email' => 'jane@example.com'],
        ])->assertStatus(401);
    }

    public function test_migration_handles_a_setting_whose_token_is_already_gone(): void
    {
        $publicToken = $this->makeLegacyPublicToken();
        PersonalAccessToken::findToken($publicToken)->delete();

        $this->runMigration();

        $this->assertDatabaseMissing('client_settings', ['name' => 'token']);
    }
}
