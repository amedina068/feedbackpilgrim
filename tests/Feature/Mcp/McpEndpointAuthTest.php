<?php

namespace Tests\Feature\Mcp;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class McpEndpointAuthTest extends TestCase
{
    use RefreshDatabase;

    private function jsonRpc(array $overrides = []): array
    {
        return array_merge([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], $overrides);
    }

    private function bearerToken(): string
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $token = $user->createToken('mcp', ['*']);
        $token->accessToken->client_id = $client->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    public function test_mcp_route_is_registered(): void
    {
        $hasRoute = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->uri() === 'mcp' && in_array('POST', $route->methods(), true));

        $this->assertTrue($hasRoute, 'Expected POST /mcp to be registered.');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/mcp', $this->jsonRpc(), ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_session_cookie_auth_is_rejected_without_bearer(): void
    {
        $user = User::factory()->create();

        // Logged in on the web guard, but the bearer-only /mcp route must still reject.
        $response = $this->actingAs($user)
            ->postJson('/mcp', $this->jsonRpc(), ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_valid_bearer_token_is_accepted(): void
    {
        $response = $this->withToken($this->bearerToken())
            ->postJson('/mcp', $this->jsonRpc(), ['Accept' => 'application/json']);

        $response->assertOk();
    }

    public function test_exceeding_throttle_returns_429(): void
    {
        $token = $this->bearerToken();

        for ($i = 0; $i < 60; $i++) {
            $this->withToken($token)->postJson('/mcp', $this->jsonRpc(), ['Accept' => 'application/json']);
        }

        $response = $this->withToken($token)->postJson('/mcp', $this->jsonRpc(), ['Accept' => 'application/json']);

        $response->assertStatus(429);
    }
}
