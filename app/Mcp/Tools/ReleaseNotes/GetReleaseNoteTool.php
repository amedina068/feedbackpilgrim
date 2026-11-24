<?php

namespace App\Mcp\Tools\ReleaseNotes;

use App\Actions\ReleaseNotes\GetReleaseNote;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch a single release note by id for the authenticated client, including tags, votes, and comments. Returns not-found if it belongs to another client.')]
class GetReleaseNoteTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'get_release_note';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new GetReleaseNote)($this->context($request), ['id' => $request->get('id')])
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The release note id to retrieve. Example: 7.'),
        ];
    }
}
