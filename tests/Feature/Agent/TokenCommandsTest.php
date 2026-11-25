<?php

namespace Tests\Feature\Agent;

use App\Models\Client;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenCommandsTest extends TestCase
{
    use RefreshDatabase;
    use RunsJsonCommands;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'jane@example.com']);
        $this->user->forceFill(['is_owner' => true])->save();
        $this->client = Client::factory()->create(['slug' => 'acme']);
        $this->user->clients()->attach($this->client);
    }

    public function test_create_makes_a_scoped_token_for_the_only_project_of_the_user(): void
    {
        $result = $this->runJson('fp:token:create', [
            '--user' => 'jane@example.com',
            '--name' => 'ci',
            '--abilities' => 'mcp:read',
            '--expires' => '30d',
        ]);

        $this->assertSame(0, $result['exit']);
        $token = $result['data']['token'];
        $this->assertSame('ci', $token['name']);
        $this->assertSame('acme', $token['project']);
        $this->assertSame(['mcp:read'], $token['abilities']);
        $this->assertMatchesRegularExpression('/^\d+\|\S+$/', $token['plain_text']);
        $this->assertSame(now()->addDays(30)->toDateString(), substr($token['expires_at'], 0, 10));

        $stored = PersonalAccessToken::query()->findOrFail($token['id']);
        $this->assertSame($this->client->id, (int) $stored->client_id);
        $this->assertSame(['mcp:read'], $stored->abilities);
    }

    public function test_create_gives_all_product_abilities_and_no_expiry_by_default(): void
    {
        $token = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'agent'])['data']['token'];

        $this->assertSame(ApiTokens::PRODUCT_ABILITIES, $token['abilities']);
        $this->assertNull($token['expires_at']);
    }

    public function test_create_refuses_an_unknown_user(): void
    {
        $result = $this->runJson('fp:token:create', ['--user' => 'nobody@example.com', '--name' => 'x']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('user_not_found', $result['errors'][0]['code']);
    }

    public function test_create_refuses_admin_or_wildcard_abilities_on_a_product_token(): void
    {
        foreach (['*', 'instance:admin', 'api,instance:admin'] as $abilities) {
            $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'x', '--abilities' => $abilities]);

            $this->assertSame(2, $result['exit'], $abilities);
            $this->assertSame('invalid_abilities', $result['errors'][0]['code']);
        }

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_create_refuses_a_bad_expiry(): void
    {
        $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'x', '--expires' => 'tomorrow']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('invalid_expiry', $result['errors'][0]['code']);
    }

    public function test_create_needs_a_project_when_the_user_has_two(): void
    {
        $this->user->clients()->attach(Client::factory()->create(['slug' => 'beta']));

        $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'x']);
        $this->assertSame(2, $result['exit']);
        $this->assertSame('project_required', $result['errors'][0]['code']);

        $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'x', '--project' => 'beta']);
        $this->assertSame(0, $result['exit']);
        $this->assertSame('beta', $result['data']['token']['project']);
    }

    public function test_create_refuses_a_project_of_another_user(): void
    {
        Client::factory()->create(['slug' => 'other']);

        $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'x', '--project' => 'other']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('project_not_found', $result['errors'][0]['code']);
    }

    public function test_admin_creates_a_short_lived_admin_token_for_an_owner(): void
    {
        $token = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'ops', '--admin' => true])['data']['token'];

        $this->assertSame([ApiTokens::INSTANCE_ADMIN], $token['abilities']);
        $this->assertNull($token['project']);
        $this->assertSame(now()->addDay()->toDateString(), substr($token['expires_at'], 0, 10));
    }

    public function test_admin_refuses_a_long_expiry_and_a_user_who_is_not_an_owner(): void
    {
        $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'ops', '--admin' => true, '--expires' => '30d']);
        $this->assertSame(2, $result['exit']);
        $this->assertSame('invalid_expiry', $result['errors'][0]['code']);

        $result = $this->runJson('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'ops', '--admin' => true, '--expires' => 'never']);
        $this->assertSame(2, $result['exit']);

        User::factory()->create(['email' => 'member@example.com']);
        $result = $this->runJson('fp:token:create', ['--user' => 'member@example.com', '--name' => 'ops', '--admin' => true]);
        $this->assertSame(2, $result['exit']);
        $this->assertSame('not_owner', $result['errors'][0]['code']);
    }

    public function test_list_shows_the_tokens_without_secrets(): void
    {
        ApiTokens::createProductToken($this->user, $this->client, 'new');
        $legacy = $this->user->createToken('old', ['*'])->accessToken;
        $legacy->forceFill(['client_id' => $this->client->id])->save();

        $result = $this->runJson('fp:token:list');

        $this->assertSame(0, $result['exit']);
        $tokens = collect($result['data']['tokens'])->keyBy('name');
        $this->assertCount(2, $tokens);
        $this->assertTrue($tokens['old']['legacy']);
        $this->assertFalse($tokens['new']['legacy']);
        $this->assertSame('jane@example.com', $tokens['new']['user']);
        $this->assertArrayNotHasKey('token', $tokens['new']);
        $this->assertArrayNotHasKey('plain_text', $tokens['new']);
    }

    public function test_list_filters_by_user(): void
    {
        ApiTokens::createProductToken($this->user, $this->client, 'mine');
        $other = User::factory()->create(['email' => 'other@example.com']);
        $other->clients()->attach($this->client);
        ApiTokens::createProductToken($other, $this->client, 'theirs');

        $tokens = $this->runJson('fp:token:list', ['--user' => 'other@example.com'])['data']['tokens'];

        $this->assertSame(['theirs'], array_column($tokens, 'name'));
    }

    public function test_revoke_deletes_the_token(): void
    {
        $token = ApiTokens::createProductToken($this->user, $this->client, 'old')->accessToken;

        $result = $this->runJson('fp:token:revoke', ['id' => $token->id]);

        $this->assertSame(0, $result['exit']);
        $this->assertSame('revoked', $result['data']['status']);
        $this->assertSame('old', $result['data']['token']['name']);
        $this->assertNull(PersonalAccessToken::query()->find($token->id));
    }

    public function test_revoke_of_an_unknown_token_is_bad_input(): void
    {
        $result = $this->runJson('fp:token:revoke', ['id' => 999]);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('token_not_found', $result['errors'][0]['code']);
    }

    public function test_create_without_json_prints_the_token_one_time(): void
    {
        $this->artisan('fp:token:create', ['--user' => 'jane@example.com', '--name' => 'ci'])
            ->expectsOutputToContain('|')
            ->expectsOutputToContain('The command does not show it again')
            ->assertSuccessful();
    }
}
