<?php

namespace App\Mcp\Tools\Testimonials;

use App\Actions\Testimonials\ListTestimonials;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List testimonials for the authenticated client, with pagination, search, rating filter, and sorting. Note: when the approved filter is omitted, only pending testimonials are returned; pass approved=true for approved ones. Use to browse social proof.')]
class ListTestimonialsTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'list_testimonials';

    public function handle(Request $request): Response
    {
        return ToolResponse::paginator(
            (new ListTestimonials)($this->context($request), $request->all())
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
            'search' => $schema->string()->description('Case-insensitive match against description and username.'),
            'approved' => $schema->boolean()->description('true returns approved testimonials; omit to return only pending.'),
            'rating' => $schema->integer()->min(1)->max(5)->description('Filter by exact star rating, 1-5.'),
            'sort_by' => $schema->string()->enum(['created_at', 'updated_at', 'rating', 'approved_at'])->description('Column to sort by.'),
            'sort_direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction. Default desc.'),
        ];
    }
}
