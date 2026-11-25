<?php

namespace Tests\Feature\Agent;

use App\Models\Client;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class McpConnectCommandTest extends TestCase
{
    use RefreshDatabase;
    use RunsJsonCommands;

    private function owner(): User
    {
        $owner = User::factory()->create(['email' => 'jane@example.com']);
        $owner->forceFill(['is_owner' => true])->save();
        $owner->clients()->attach(Client::factory()->create(['slug' => 'acme']));

        return $owner;
    }

    public function test_claude_prints_the_add_command_that_reads_the_token_from_the_environment(): void
    {
        $result = $this->runJson('fp:mcp:connect', ['--client' => 'claude']);

        $this->assertSame(0, $result['exit']);
        $this->assertSame('claude', $result['data']['client']);
        $this->assertSame(
            'claude mcp add --transport http feedback-pilgrim http://localhost/mcp --header "Authorization: Bearer ${FP_TOKEN}"',
            $result['data']['command'],
        );
        $this->assertNull($result['data']['token']);
        $this->assertNull($result['data']['env']);
    }

    public function test_codex_prints_the_add_command_and_the_config_snippet(): void
    {
        $data = $this->runJson('fp:mcp:connect', ['--client' => 'codex'])['data'];

        $this->assertSame('codex mcp add feedback-pilgrim --url http://localhost/mcp --bearer-token-env-var FP_TOKEN', $data['command']);
        $this->assertStringContainsString('[mcp_servers.feedback-pilgrim]', $data['config']);
        $this->assertStringContainsString('bearer_token_env_var = "FP_TOKEN"', $data['config']);
    }

    public function test_cursor_prints_the_mcp_json_config(): void
    {
        $data = $this->runJson('fp:mcp:connect', ['--client' => 'cursor'])['data'];

        $this->assertNull($data['command']);
        $this->assertSame('http://localhost/mcp', $data['config']['mcpServers']['feedback-pilgrim']['url']);
        $this->assertSame('Bearer ${env:FP_TOKEN}', $data['config']['mcpServers']['feedback-pilgrim']['headers']['Authorization']);
    }

    public function test_json_prints_a_generic_config(): void
    {
        $data = $this->runJson('fp:mcp:connect', ['--client' => 'json'])['data'];

        $this->assertSame('http', $data['config']['mcpServers']['feedback-pilgrim']['type']);
        $this->assertSame('Bearer ${FP_TOKEN}', $data['config']['mcpServers']['feedback-pilgrim']['headers']['Authorization']);
    }

    public function test_a_new_token_is_created_with_the_mcp_abilities(): void
    {
        $this->owner();

        $data = $this->runJson('fp:mcp:connect', ['--client' => 'json', '--token' => 'new'])['data'];

        $this->assertSame([ApiTokens::MCP_READ, ApiTokens::MCP_WRITE], $data['token']['abilities']);
        $this->assertSame("export FP_TOKEN='{$data['token']['plain_text']}'", $data['env']);
        $this->assertSame('Bearer '.$data['token']['plain_text'], $data['config']['mcpServers']['feedback-pilgrim']['headers']['Authorization']);
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_a_new_token_needs_a_user_when_the_instance_has_no_owner(): void
    {
        $result = $this->runJson('fp:mcp:connect', ['--client' => 'claude', '--token' => 'new']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('user_not_found', $result['errors'][0]['code']);
    }

    public function test_an_unknown_client_is_bad_input(): void
    {
        $result = $this->runJson('fp:mcp:connect', ['--client' => 'vim']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('invalid_client', $result['errors'][0]['code']);
    }

    public function test_without_json_it_prints_the_command(): void
    {
        $this->artisan('fp:mcp:connect', ['--client' => 'claude'])
            ->expectsOutputToContain('claude mcp add --transport http feedback-pilgrim')
            ->expectsOutputToContain('export FP_TOKEN')
            ->assertSuccessful();
    }
}
