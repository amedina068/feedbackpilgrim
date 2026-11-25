<?php

namespace App\Mcp\Tools\Posts;

use App\Actions\Posts\ListPosts;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List feedback posts for the authenticated client, with pagination, search, status/board/tag filters, and sorting. Returns a paginated envelope of posts (title, content, status, tags, contact, votes). Use to browse or find feature requests and feedback.')]
#[IsReadOnly]
class ListPostsTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'list_posts';

    public function handle(Request $request): Response
    {
        return ToolResponse::paginator(
            (new ListPosts)($this->context($request), $request->all())
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
            'search' => $schema->string()->description('Case-insensitive match against title and content. Example: "dark mode".'),
            'status' => $schema->string()->enum(['pending', 'in_progress', 'completed'])->description('Filter by workflow status.'),
            'board_id' => $schema->integer()->description('Filter to the posts on this board. Example: 1.'),
            'tag' => $schema->string()->description('Filter to posts carrying this tag title. Example: "bug".'),
            'published' => $schema->boolean()->description('true returns only published posts, false only unpublished.'),
            'sort_by' => $schema->string()->enum(['created_at', 'updated_at', 'title', 'status', 'published_at'])->description('Column to sort by. Default created_at.'),
            'sort_direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction. Default desc.'),
        ];
    }
}
