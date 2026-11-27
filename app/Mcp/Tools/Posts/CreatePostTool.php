<?php

namespace App\Mcp\Tools\Posts;

use App\Actions\Posts\CreatePost;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Create a feedback post for the authenticated client. Not idempotent — calling again creates another post, so avoid blind retries. Returns the created post with tags and contact.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class CreatePostTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'create_post';

    public function handle(Request $request): Response
    {
        return $this->guarded(fn () => ToolResponse::item(
            (new CreatePost)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('Post title. Example: "Add dark mode".'),
            'content' => $schema->string()->required()->description('Post body. Example: "Users want a dark theme."'),
            'status' => $schema->string()->enum(['pending', 'in_progress', 'completed'])->description('Workflow status. Defaults to pending.'),
            'board_id' => $schema->integer()->description('Board id to file the post under. Must belong to your client. Defaults to the client\'s first public board, or to the first board if no board is public.'),
            'tags' => $schema->array()->items($schema->integer())->description('Tag ids to attach. Must belong to your client. Example: [3, 8].'),
        ];
    }
}
