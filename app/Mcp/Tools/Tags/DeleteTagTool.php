<?php

namespace App\Mcp\Tools\Tags;

use App\Actions\Tags\DeleteTag;
use App\Mcp\Concerns\ResolvesClientContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a tag by id for the authenticated client. The tag is detached from all posts, contacts and release notes, then removed. Returns not-found if it belongs to another client. This is the only delete operation the API exposes.')]
class DeleteTagTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'delete_tag';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(function () use ($request) {
            (new DeleteTag)($this->context($request), $request->all());

            return Response::json(['deleted' => true, 'id' => (int) $request->get('id')]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The tag id to delete. Example: 9.'),
        ];
    }
}
