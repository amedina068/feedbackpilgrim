<?php

namespace Tests\Feature\Mcp\Admin;

use App\Models\Client;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The optional HTTP route of the admin MCP server. It exists only when
 * MCP_ADMIN_ENABLED is true, and it accepts only a short-lived owner token
 * with the `instance:admin` ability.
 */
class AdminHttpRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->owner->forceFill(['is_owner' => true])->save();
        $this->owner->clients()->attach(Client::factory()->create());
    }

    private function enableAdminRoute(): void
    {
        config(['mcp.admin.http_enabled' => true]);
        require base_path('routes/ai.php');
        app('router')->getRoutes()->refreshNameLookups();
    }

    private function listTools(string $token): TestResponse
    {
        // The Sanctum guard keeps the user of the first request of a test.
        app('auth')->forgetGuards();

        return $this->withToken($token)->postJson('/mcp/admin', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], ['Accept' => 'application/json']);
    }

    public function test_the_route_does_not_exist_by_default(): void
    {
        $token = ApiTokens::createAdminToken($this->owner, 'ops', now()->addDay())->plainTextToken;

        $this->listTools($token)->assertNotFound();
    }

    public function test_an_owner_admin_token_lists_the_admin_tools(): void
    {
        $this->enableAdminRoute();
        $token = ApiTokens::createAdminToken($this->owner, 'ops', now()->addDay())->plainTextToken;

        $response = $this->listTools($token)->assertOk();

        $this->assertContains('get-instance-status', $response->json('result.tools.*.name'));
    }

    public function test_an_http_call_is_audited_with_the_owner(): void
    {
        $this->enableAdminRoute();
        $token = ApiTokens::createAdminToken($this->owner, 'ops', now()->addDay())->plainTextToken;
        $directory = sys_get_temp_dir().'/fp-audit-'.uniqid();
        config(['logging.channels.mcp-admin.path' => $directory.'/mcp-admin.log']);

        try {
            $this->withToken($token)->postJson('/mcp/admin', [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => ['name' => 'list-users', 'arguments' => (object) []],
            ], ['Accept' => 'application/json'])->assertOk();

            $files = glob($directory.'/mcp-admin*.log');
            $this->assertCount(1, $files);
            $entry = json_decode(file($files[0], FILE_IGNORE_NEW_LINES)[0], true)['context'];
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }

        $this->assertSame('list-users', $entry['tool']);
        $this->assertSame('http', $entry['transport']);
        $this->assertSame($this->owner->id, $entry['user']['id']);
    }

    public function test_product_and_legacy_tokens_are_refused(): void
    {
        $this->enableAdminRoute();
        $client = $this->owner->clients()->firstOrFail();

        $product = ApiTokens::createProductToken($this->owner, $client, 'agent')->plainTextToken;
        $this->listTools($product)->assertForbidden();

        $legacy = $this->owner->createToken('old', ['*'])->plainTextToken;
        $this->listTools($legacy)->assertForbidden();

        $shortLivedWildcard = $this->owner->createToken('old', ['*'], now()->addDay())->plainTextToken;
        $this->listTools($shortLivedWildcard)->assertForbidden();

        $mixed = $this->owner->createToken('mixed', [ApiTokens::INSTANCE_ADMIN, ApiTokens::API], now()->addDay())->plainTextToken;
        $this->listTools($mixed)->assertForbidden();
    }

    public function test_an_admin_token_without_a_short_expiry_is_refused(): void
    {
        $this->enableAdminRoute();

        $noExpiry = $this->owner->createToken('ops', [ApiTokens::INSTANCE_ADMIN])->plainTextToken;
        $this->listTools($noExpiry)->assertForbidden();

        $long = $this->owner->createToken('ops', [ApiTokens::INSTANCE_ADMIN], now()->addDays(30))->plainTextToken;
        $this->listTools($long)->assertForbidden();
    }

    public function test_an_admin_token_of_a_user_who_is_not_the_owner_is_refused(): void
    {
        $this->enableAdminRoute();
        $member = User::factory()->create();

        $token = $member->createToken('ops', [ApiTokens::INSTANCE_ADMIN], now()->addDay())->plainTextToken;
        $this->listTools($token)->assertForbidden();

        $ownerToken = ApiTokens::createAdminToken($this->owner, 'ops', now()->addDay())->plainTextToken;
        $this->owner->forceFill(['is_owner' => false])->save();
        $this->listTools($ownerToken)->assertForbidden();
    }
}
