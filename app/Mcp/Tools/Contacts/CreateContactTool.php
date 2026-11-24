<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Support\ContactPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a contact for the authenticated client, matched by email (upsert). Custom fields beyond the documented ones are stored as metadata. The response includes was_created (true if a new contact was made, false if an existing one was updated).')]
class CreateContactTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'create_contact';

    public function handle(Request $request): Response
    {
        return $this->guarded(function () use ($request) {
            $result = (new CreateContact)($this->context($request), $request->all());

            return Response::json([
                ...ContactPresenter::present($result->contact),
                'was_created' => $result->wasCreated,
            ]);
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Contact name. Example: "Jane Doe".'),
            'email' => $schema->string()->required()->description('Contact email; the upsert key. Example: "jane@acme.com".'),
            'avatar' => $schema->string()->description('Avatar URL.'),
            'mrr' => $schema->number()->description('Monthly recurring revenue.'),
            'ltv' => $schema->number()->description('Lifetime value.'),
            'tags' => $schema->array()->items($schema->integer())->description('Tag ids to attach. Must belong to your client.'),
        ];
    }
}
