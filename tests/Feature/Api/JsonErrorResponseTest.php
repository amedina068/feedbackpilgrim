<?php

namespace Tests\Feature\Api;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Posts\GetPostTool;
use App\Mcp\Tools\ReleaseNotes\UpdateReleaseNoteTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Many API clients send no Accept header, for example curl and short scripts.
 * Every API error must still come back as JSON with a "message" that says
 * what went wrong. It must never be a redirect or an HTML page.
 */
class JsonErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);

        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->plainToken = $token->plainTextToken;
    }

    public function test_wrong_token_without_accept_header_returns_json_401(): void
    {
        $response = $this->get('/api/public/v1/posts', ['Authorization' => 'Bearer wrong']);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('Authorization', $response->json('message'));
    }

    public function test_validation_error_without_accept_header_returns_json_422(): void
    {
        $response = $this->post('/api/public/v1/posts', [], ['Authorization' => 'Bearer '.$this->plainToken]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_unknown_api_route_without_accept_header_returns_json_404(): void
    {
        $response = $this->get('/api/public/v1/nothing', ['Authorization' => 'Bearer '.$this->plainToken]);

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_missing_record_returns_a_message_that_names_the_record(): void
    {
        $response = $this->getJson('/api/public/v1/release-notes/999', ['Authorization' => 'Bearer '.$this->plainToken]);

        $response->assertStatus(404);
        $response->assertExactJson([
            'message' => 'No release note with ID 999 exists for the client of this token.',
        ]);
    }

    public function test_token_without_client_returns_message_and_error(): void
    {
        $token = $this->user->createToken('no-client', ['*']);

        $response = $this->get('/api/public/v1/posts', ['Authorization' => 'Bearer '.$token->plainTextToken]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Invalid API token');
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_widget_init_with_wrong_key_returns_message_and_error(): void
    {
        $response = $this->post('/api/v1/init', [], ['Authorization' => 'Bearer pk_wrong']);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Invalid client key');
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_widget_init_with_wrong_user_hash_returns_message_and_error(): void
    {
        $this->client->update(['require_identity_verification' => true]);

        $response = $this->post('/api/v1/init', [
            'contact' => ['email' => 'jane@example.com'],
            'userHash' => 'wrong',
        ], ['Authorization' => 'Bearer '.$this->client->publishable_key]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'Identity verification failed');
        $this->assertStringContainsString('identity secret', $response->json('message'));
    }

    public function test_mcp_tool_names_the_missing_record(): void
    {
        $this->user->withAccessToken($this->user->tokens()->first());

        PublicServer::actingAs($this->user)
            ->tool(GetPostTool::class, ['id' => 42])
            ->assertHasErrors(['No post with ID 42 exists for the client of this token.']);

        PublicServer::actingAs($this->user)
            ->tool(UpdateReleaseNoteTool::class, ['id' => 7, 'title' => 'New title'])
            ->assertHasErrors(['No release note with ID 7 exists for the client of this token.']);
    }
}
