<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the users of the instance with their projects, owner flag and status. Filter with search (name or email).')]
#[IsReadOnly]
class ListUsersTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'list-users';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $request->validate(['search' => 'nullable|string|max:255']);

            return Response::json(['users' => self::users((string) $request->get('search', ''))]);
        });
    }

    /**
     * The same view of a user as `fp:user:list --json`.
     *
     * @return list<array{id: int, name: string, email: string, projects: list<string>, owner: bool, status: string, created_at: string|null}>
     */
    public static function users(string $search = ''): array
    {
        $search = trim($search);

        return User::query()
            ->with(['clients' => fn ($query) => $query->select('clients.id', 'clients.name')->orderBy('clients.name')])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';

                $query->where(fn ($query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'projects' => $user->clients->pluck('name')->values()->all(),
                'owner' => $user->isOwner(),
                'status' => $user->isDeactivated() ? 'deactivated' : 'active',
                'created_at' => $user->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Show only the users whose name or email contains this text. Example: "jane".'),
        ];
    }
}
