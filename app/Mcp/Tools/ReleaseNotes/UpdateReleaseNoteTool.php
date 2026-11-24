<?php

namespace App\Mcp\Tools\ReleaseNotes;

use App\Actions\ReleaseNotes\UpdateReleaseNote;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing release note by id for the authenticated client. Only supplied fields change. Returns not-found if it belongs to another client.')]
class UpdateReleaseNoteTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'update_release_note';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new UpdateReleaseNote)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The release note id to update. Example: 7.'),
            'title' => $schema->string()->description('New title.'),
            'content' => $schema->string()->description('New body.'),
            'published' => $schema->boolean()->description('true publishes the note and keeps a publication date that exists. false clears the publication date (draft).'),
            'tags' => $schema->array()->items($schema->integer())->description('Replacement tag ids (sync). Must belong to your client.'),
        ];
    }
}
