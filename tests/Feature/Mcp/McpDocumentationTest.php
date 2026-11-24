<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Support\ToolCatalog;
use Tests\TestCase;

class McpDocumentationTest extends TestCase
{
    public function test_documentation_page_renders_mcp_section_and_every_tool(): void
    {
        $response = $this->get(route('documentation.reference'));

        $response->assertOk();
        $response->assertSee('MCP server');
        $response->assertSee('id="mcp"', false);

        foreach (ToolCatalog::all() as $tool) {
            $response->assertSee($tool['name']);
        }
    }

    public function test_catalog_is_data_driven_from_registered_tools(): void
    {
        $catalogNames = array_map(fn ($tool) => $tool['name'], ToolCatalog::all());

        $registeredNames = array_map(
            fn (string $class) => (new $class)->name(),
            PublicServer::toolClasses(),
        );

        sort($catalogNames);
        sort($registeredNames);

        $this->assertSame($registeredNames, $catalogNames);
        $this->assertCount(21, $catalogNames);
    }
}
