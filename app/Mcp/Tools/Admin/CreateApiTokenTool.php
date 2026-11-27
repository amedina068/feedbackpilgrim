<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Create a product API token for one user and one project. The abilities are mcp:read, mcp:write and api (default: all three). The tool never creates an admin token. The response holds the token value one time.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class CreateApiTokenTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'create-api-token';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $input = $request->validate([
                'user_email' => 'required|string|email',
                'name' => 'required|string|max:100',
                'project' => 'nullable|string',
                'abilities' => 'nullable|array',
                'abilities.*' => 'string',
                'expires_in_days' => 'nullable|integer|min:1|max:3650',
            ]);

            $user = User::query()->where('email', $input['user_email'])->first();

            if (! $user) {
                return Response::error("No user has the email {$input['user_email']}.");
            }

            try {
                $client = ApiTokens::projectFor($user, $input['project'] ?? null);
                $token = ApiTokens::createProductToken(
                    $user,
                    $client,
                    $input['name'],
                    $input['abilities'] ?? ApiTokens::PRODUCT_ABILITIES,
                    isset($input['expires_in_days']) ? now()->addDays((int) $input['expires_in_days']) : null,
                );
            } catch (InvalidArgumentException $exception) {
                return Response::error($exception->getMessage());
            }

            return Response::json([
                'token' => array_merge(ApiTokens::describe($token->accessToken), ['plain_text' => $token->plainTextToken]),
                'message' => 'Keep the token now. No tool shows it again.',
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_email' => $schema->string()->required()->description('The email of the user who owns the token.'),
            'name' => $schema->string()->required()->description('A name for the token. Example: "zapier".'),
            'project' => $schema->string()->description('The subdomain of the project. Required when the user has two or more projects.'),
            'abilities' => $schema->array()->items($schema->string()->enum(ApiTokens::PRODUCT_ABILITIES))->description('The abilities. Default: mcp:read, mcp:write and api.'),
            'expires_in_days' => $schema->integer()->min(1)->max(3650)->description('The token expires after this number of days. Default: no expiry.'),
        ];
    }
}
