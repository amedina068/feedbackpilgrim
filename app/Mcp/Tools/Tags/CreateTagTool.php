<?php

namespace App\Mcp\Tools\Tags;

use App\Actions\Tags\CreateTag;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a tag for the authenticated client. Titles are unique per client. Not idempotent — avoid blind retries.')]
class CreateTagTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'create_tag';

    public function handle(Request $request): Response
    {
        return $this->guarded(fn () => ToolResponse::item(
            (new CreateTag)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('Tag title, unique within your client. Example: "bug".'),
        ];
    }
}
