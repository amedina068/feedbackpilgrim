<?php

namespace Tests\Feature\Mcp;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The MCP server authenticates with Sanctum tokens. A token stops working when
 * the owner deactivates its user. Reactivation restores it.
 */
class McpDeactivatedUserTest extends TestCase
{
    use RefreshDatabase;

    private function callMcp(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);
    }

    public function test_a_token_stops_working_and_works_again_after_reactivation(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $token = $user->createToken('mcp', ['*']);
        $token->accessToken->client_id = $client->id;
        $token->accessToken->save();

        $this->callMcp($token->plainTextToken)->assertOk();

        $user->deactivate();
        $this->callMcp($token->plainTextToken)->assertUnauthorized();

        $user->reactivate();
        $this->callMcp($token->plainTextToken)->assertOk();
    }
}
