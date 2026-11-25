<?php

namespace App\Mcp\Tools\Tags;

use App\Actions\Tags\ListTags;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List tags for the authenticated client, with pagination, search, and sorting. Each tag includes counts of posts and contacts. Use to discover available tag titles before filtering other resources by tag.')]
#[IsReadOnly]
class ListTagsTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'list_tags';

    public function handle(Request $request): Response
    {
        return ToolResponse::paginator(
            (new ListTags)($this->context($request), $request->all())
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1)->description('Page number to retrieve. Example: 1.'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Records per page (max 100, default 15). Example: 25.'),
            'search' => $schema->string()->description('Case-insensitive match against tag title. Example: "bug".'),
            'sort_by' => $schema->string()->enum(['title', 'created_at', 'updated_at'])->description('Column to sort by. Default title.'),
            'sort_direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction. Default asc.'),
        ];
    }
}
