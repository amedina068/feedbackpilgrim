<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Contacts\GetContact;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch a single contact by id for the authenticated client, including tags and votes, with custom metadata fields flattened onto the record. Returns not-found if it belongs to another client.')]
class GetContactTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'get_contact';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new GetContact)($this->context($request), ['id' => $request->get('id')])
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The contact id to retrieve. Example: 12.'),
        ];
    }
}
