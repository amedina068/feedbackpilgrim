<?php

namespace App\Mcp\Tools\Admin;

use App\Actions\Clients\CreateClientForOwner;
use App\Mcp\Concerns\AuditsAdminCalls;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Create a project with its public site. The user with owner_email (default: the first owner) gets access to it and becomes its admin contact. The subdomain must be lowercase letters, digits and hyphens, and must not be taken or reserved.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class CreateProjectTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'create-project';

    public function handle(Request $request, CreateClientForOwner $createClient): Response
    {
        return $this->audited($request, function () use ($request, $createClient) {
            $input = $request->validate([
                'name' => 'required|string|max:255',
                'subdomain' => CreateClientForOwner::subdomainRules(),
                'owner_email' => 'nullable|string|email|exists:users,email',
            ]);

            $user = isset($input['owner_email'])
                ? User::query()->where('email', $input['owner_email'])->firstOrFail()
                : User::query()->where('is_owner', true)->orderBy('id')->first();

            if (! $user) {
                return Response::error('The instance has no owner. Run php artisan fp:install first, or give owner_email.');
            }

            $client = $createClient($user, $input['name'], $input['subdomain']);

            return Response::json([
                'project' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'subdomain' => $client->slug,
                    'public_url' => $client->public_site,
                ],
                'member' => $user->email,
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The name of the project. Example: "Acme".'),
            'subdomain' => $schema->string()->required()->description('The subdomain of the public site. Example: "acme".'),
            'owner_email' => $schema->string()->description('The user who gets access. Default: the first owner.'),
        ];
    }
}
