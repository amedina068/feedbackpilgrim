<?php

namespace App\Mcp\Tools\ReleaseNotes;

use App\Actions\ReleaseNotes\ListReleaseNotes;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List release notes for the authenticated client, with pagination, search, published filter, tag filter, and sorting. Returns a paginated envelope (title, content, slug, published_at, tags). Use to browse the changelog.')]
#[IsReadOnly]
class ListReleaseNotesTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'list_release_notes';

    public function handle(Request $request): Response
    {
        return ToolResponse::paginator(
            (new ListReleaseNotes)($this->context($request), $request->all())
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
            'search' => $schema->string()->description('Case-insensitive match against title and content. Example: "v2".'),
            'published' => $schema->boolean()->description('true returns only published notes, false only drafts.'),
            'tag' => $schema->string()->description('Filter to release notes carrying this tag title.'),
            'sort_by' => $schema->string()->enum(['created_at', 'updated_at', 'title', 'published_at'])->description('Column to sort by. Default created_at.'),
            'sort_direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction. Default desc.'),
        ];
    }
}
