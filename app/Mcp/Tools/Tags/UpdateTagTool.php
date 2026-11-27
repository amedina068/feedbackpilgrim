<?php

namespace App\Mcp\Tools\Tags;

use App\Actions\Tags\UpdateTag;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Rename a tag by id for the authenticated client. The new title must be unique within your client. Returns not-found if it belongs to another client.')]
#[IsReadOnly(false)]
#[IsDestructive(true)]
class UpdateTagTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'update_tag';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new UpdateTag)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The tag id to update. Example: 9.'),
            'title' => $schema->string()->required()->description('New tag title, unique within your client.'),
        ];
    }
}
