<?php

namespace Tests\Feature\Agent;

use App\Mcp\Servers\PublicServer;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * A token reaches only the surfaces that its abilities allow. Tokens from
 * before the abilities have `*` and keep working on the product surfaces.
 */
class TokenAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->forceFill(['is_owner' => true])->save();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function token(array $abilities, ?\DateTimeInterface $expiresAt = null): string
    {
        $token = $this->user->createToken('test', $abilities, $expiresAt);
        $token->accessToken->forceFill(['client_id' => $this->client->id])->save();

        return $token->plainTextToken;
    }

    private function rest(string $token): TestResponse
    {
        return $this->withToken($token)->getJson('/api/public/v1/posts');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function mcp(string $token, string $method = 'tools/list', array $params = []): TestResponse
    {
        return $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], ['Accept' => 'application/json']);
    }

    /**
     * @return list<string>
     */
    private function toolNames(TestResponse $response): array
    {
        return $response->json('result.tools.*.name') ?? [];
    }

    public function test_a_legacy_wildcard_token_keeps_working_on_rest_and_mcp(): void
    {
        $token = $this->token(['*']);

        $this->rest($token)->assertOk();

        $tools = $this->toolNames($this->mcp($token)->assertOk());
        $this->assertContains('list_posts', $tools);
        $this->assertContains('create_post', $tools);
    }

    public function test_an_api_token_cannot_use_mcp(): void
    {
        $token = $this->token([ApiTokens::API]);

        $this->rest($token)->assertOk();
        $this->mcp($token)->assertForbidden()->assertJsonPath('error', 'insufficient_ability');
    }

    public function test_a_read_only_mcp_token_sees_and_calls_only_read_tools(): void
    {
        $token = $this->token([ApiTokens::MCP_READ]);

        $this->rest($token)->assertForbidden()->assertJsonPath('error', 'insufficient_ability');

        $tools = $this->toolNames($this->mcp($token)->assertOk());
        $this->assertContains('list_posts', $tools);
        $this->assertContains('get_post', $tools);
        $this->assertNotContains('create_post', $tools);
        $this->assertNotContains('delete_tag', $tools);

        $call = $this->mcp($token, 'tools/call', ['name' => 'create_post', 'arguments' => ['title' => 'x']]);
        $this->assertNotNull($call->json('error'));
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_write_mcp_token_can_create_a_post(): void
    {
        $token = $this->token([ApiTokens::MCP_READ, ApiTokens::MCP_WRITE]);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);

        $this->assertContains('create_post', $this->toolNames($this->mcp($token)));
    }

    public function test_an_expired_token_is_refused(): void
    {
        $token = $this->token(ApiTokens::PRODUCT_ABILITIES, now()->addDay());

        $this->travel(2)->days();

        $this->rest($token)->assertUnauthorized();
        $this->mcp($token)->assertUnauthorized();
    }

    public function test_an_admin_token_cannot_use_the_product_surfaces(): void
    {
        $token = ApiTokens::createAdminToken($this->user, 'ops', now()->addDay())->plainTextToken;

        $this->rest($token)->assertStatus(401);
        $this->mcp($token)->assertStatus(401);
    }

    public function test_every_product_tool_has_a_read_only_hint(): void
    {
        foreach (PublicServer::toolClasses() as $toolClass) {
            $annotations = (new $toolClass)->annotations();

            $this->assertArrayHasKey('readOnlyHint', $annotations, $toolClass);
        }
    }

    public function test_the_dashboard_creates_scoped_tokens(): void
    {
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post('/settings/api-keys', ['name' => 'Zapier'])
            ->assertSessionHas('new_token');

        $token = PersonalAccessToken::query()->where('name', 'Zapier')->firstOrFail();
        $this->assertSame(ApiTokens::PRODUCT_ABILITIES, $token->abilities);
        $this->assertNull($token->expires_at);
    }

    public function test_the_dashboard_sets_the_access_and_the_expiry(): void
    {
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post('/settings/api-keys', ['name' => 'Reader', 'access' => 'mcp_read', 'expires_in_days' => '30'])
            ->assertSessionHas('new_token');

        $token = PersonalAccessToken::query()->where('name', 'Reader')->firstOrFail();
        $this->assertSame([ApiTokens::MCP_READ], $token->abilities);
        $this->assertSame(now()->addDays(30)->toDateString(), $token->expires_at->toDateString());
    }

    public function test_the_dashboard_refuses_an_unknown_access_value(): void
    {
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post('/settings/api-keys', ['name' => 'Bad', 'access' => 'admin'])
            ->assertSessionHasErrors('access');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_the_dashboard_shows_the_access_and_the_expiry_of_each_token(): void
    {
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
        $this->token(['*']);
        $this->token([ApiTokens::MCP_READ], now()->addDays(10));

        $this->actingAs($this->user)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertSee('Full access (old token)')
            ->assertSee('MCP read')
            ->assertSee('expires');
    }
}
