<?php

namespace App\Mcp\Tools\Posts;

use App\Actions\Posts\CreatePostComment;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Reply to a post as the user that owns the API token. The comment is attributed to that user\'s admin contact inside the authenticated client, and the post author is notified by email. Not idempotent — calling again adds another comment, so avoid blind retries. Returns the created comment with its contact.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class CreatePostCommentTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'create_post_comment';

    public function handle(Request $request): Response
    {
        $request->validate(['post_id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new CreatePostComment)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('The post id to reply to. Must belong to your client. Example: 42.'),
            'content' => $schema->string()->required()->description('Comment body. Example: "Thanks, this ships next week."'),
        ];
    }
}
