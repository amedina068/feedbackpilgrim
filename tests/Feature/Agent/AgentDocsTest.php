<?php

namespace Tests\Feature\Agent;

use App\Mcp\Servers\PublicServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Attributes\Instructions;
use Tests\TestCase;

/**
 * `llms.txt` and `skill.md` render on each request from the same sources as
 * the docs and the MCP server.
 */
class AgentDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_llms_txt_points_a_model_at_the_docs_and_the_install_command(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $text = $response->getContent();
        $this->assertStringStartsWith('# Feedback Pilgrim', $text);
        $this->assertStringContainsString('http://localhost/documentation/guides/quick-start', $text);
        $this->assertStringContainsString('http://localhost/documentation/openapi.json', $text);
        $this->assertStringContainsString('http://localhost/skill.md', $text);
        $this->assertStringContainsString('## API', $text);
        $this->assertStringContainsString('Posts', $text);
        $this->assertStringContainsString('## Install with an agent', $text);
        $this->assertStringContainsString('fp:install --name=', $text);
        $this->assertStringContainsString('--with-token=agent --json', $text);
        $this->assertStringContainsString('mcp:start fp-admin', $text);
    }

    public function test_skill_md_carries_the_mcp_workflow(): void
    {
        $response = $this->get('/skill.md');

        $response->assertOk();
        $this->assertStringStartsWith('text/markdown', $response->headers->get('Content-Type'));

        $text = $response->getContent();
        $this->assertStringStartsWith("---\nname: feedback-pilgrim\ndescription: ", $text);
        $this->assertStringContainsString('Connect: http://localhost/mcp', $text);
        $this->assertStringContainsString(PublicServer::WORKFLOW, $text);
    }

    public function test_the_mcp_server_sends_the_same_workflow_as_its_instructions(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ]);

        $this->assertNotSame(500, $response->status());

        $reflection = new \ReflectionClass(PublicServer::class);
        $attribute = $reflection->getAttributes(Instructions::class)[0]->newInstance();
        $this->assertSame(PublicServer::WORKFLOW, $attribute->value);
    }
}
