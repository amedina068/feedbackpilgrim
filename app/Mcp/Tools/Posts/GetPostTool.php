<?php

namespace App\Mcp\Tools\Posts;

use App\Actions\Posts\GetPost;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Fetch a single feedback post by id for the authenticated client, including contact, tags, votes, and comments. Returns not-found if the post belongs to another client. Use to inspect one post in detail.')]
#[IsReadOnly]
class GetPostTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'get_post';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new GetPost)($this->context($request), ['id' => $request->get('id')])
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The post id to retrieve. Example: 42.'),
        ];
    }
}
