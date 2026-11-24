<?php

namespace App\Mcp\Tools\ReleaseNotes;

use App\Actions\ReleaseNotes\CreateReleaseNote;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a release note for the authenticated client. Without published=true it starts as a draft. It gets a date-suffixed slug and is authored by the token owner. Not idempotent — avoid blind retries.')]
class CreateReleaseNoteTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'create_release_note';

    public function handle(Request $request): Response
    {
        return $this->guarded(fn () => ToolResponse::item(
            (new CreateReleaseNote)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('Release note title. Example: "v2.1 released".'),
            'content' => $schema->string()->required()->description('Release note body (markdown allowed).'),
            'published' => $schema->boolean()->description('true publishes the note now. Omit it or send false for a draft.'),
            'tags' => $schema->array()->items($schema->integer())->description('Tag ids to attach. Must belong to your client.'),
        ];
    }
}
