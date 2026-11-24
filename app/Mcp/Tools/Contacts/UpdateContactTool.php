<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Contacts\UpdateContact;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing contact by id for the authenticated client. Only supplied fields change. Returns not-found if it belongs to another client.')]
class UpdateContactTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'update_contact';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new UpdateContact)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The contact id to update. Example: 12.'),
            'name' => $schema->string()->description('New name.'),
            'email' => $schema->string()->description('New email (unique per client).'),
            'avatar' => $schema->string()->description('New avatar URL.'),
            'mrr' => $schema->number()->description('Monthly recurring revenue.'),
            'ltv' => $schema->number()->description('Lifetime value.'),
            'tags' => $schema->array()->items($schema->integer())->description('Replacement tag ids (sync). Must belong to your client.'),
        ];
    }
}
