<?php

namespace App\Mcp\Tools\Posts;

use App\Actions\Posts\UpdatePost;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Update an existing feedback post by id for the authenticated client. Only supplied fields change. Returns not-found if the post belongs to another client.')]
#[IsReadOnly(false)]
#[IsDestructive(true)]
class UpdatePostTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'update_post';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new UpdatePost)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The post id to update. Example: 42.'),
            'title' => $schema->string()->description('New title.'),
            'content' => $schema->string()->description('New body.'),
            'status' => $schema->string()->enum(['pending', 'in_progress', 'completed'])->description('New workflow status. A change moves the post to the matching stage and notifies the author, the voters, the webhooks and Slack.'),
            'board_id' => $schema->integer()->description('Move the post to this board. Must belong to your client.'),
            'tags' => $schema->array()->items($schema->integer())->description('Replacement tag ids (sync). Must belong to your client.'),
        ];
    }
}
