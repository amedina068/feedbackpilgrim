<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Models\Client;
use App\Models\User;
use App\Support\LoginLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Create an account for a person and return a one-time login link that works for 7 days. Feedback Pilgrim sends no email for this: give the link to the person. With project (a subdomain), the person also gets access to that project. The new user is never an owner.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class InviteUserTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'invite-user';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $input = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
                'project' => 'nullable|string|exists:clients,slug',
            ]);

            $user = DB::transaction(function () use ($input) {
                $client = isset($input['project']) ? Client::query()->where('slug', $input['project'])->firstOrFail() : null;

                $user = User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => Str::password(40),
                    'onboarding' => $client ? User::ONBOARDING_COMPLETED : User::ONBOARDING_CLIENT,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                if ($client) {
                    $user->clients()->attach($client);
                    $user->adminContactFor($client);
                }

                return $user;
            });

            $expiresAt = now()->addDays(LoginLink::INVITE_DAYS);

            return Response::json([
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'project' => $input['project'] ?? null,
                'login_url' => LoginLink::make($user, $expiresAt),
                'login_url_expires_at' => $expiresAt->toIso8601String(),
                'message' => 'Give the login link to the person. It works one time. After the login, the person sets a password.',
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The name of the person. Example: "Bob Lee".'),
            'email' => $schema->string()->required()->description('The email of the person, in lowercase. Example: "bob@example.com".'),
            'project' => $schema->string()->description('The subdomain of a project to give access to. Example: "acme". See list-projects.'),
        ];
    }
}
