<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\ApiTokens;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\PersonalAccessToken;

#[Description('Revoke one API token by id. The apps that use the token lose access at once. Without confirm: true, the tool returns a preview and changes nothing.')]
#[IsReadOnly(false)]
#[IsDestructive(true)]
class RevokeApiTokenTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'revoke-api-token';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $request->validate(['id' => 'required|integer', 'confirm' => 'nullable|boolean']);

            $token = PersonalAccessToken::query()->find((int) $request->get('id'));

            if (! $token) {
                return Response::error('No API token has the id '.(int) $request->get('id').'. See list-api-tokens.');
            }

            $description = ApiTokens::describe($token);

            if (! $this->confirmed($request)) {
                return $this->preview($description, 'revoke this token');
            }

            $token->delete();

            return Response::json(['status' => 'revoked', 'token' => $description]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The id of the token. See list-api-tokens.'),
            'confirm' => $schema->boolean()->description('Set to true after the person agrees to the preview.'),
        ];
    }
}
