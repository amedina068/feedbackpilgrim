<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\ApiTokens;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\PersonalAccessToken;

#[Description('List every API token of the instance: id, name, user, project, abilities, expiry and last use. legacy is true for a token with full access ("*") from before the token abilities. The tool never returns a token value.')]
#[IsReadOnly]
class ListApiTokensTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'list-api-tokens';

    public function handle(Request $request): Response
    {
        return $this->audited($request, fn () => Response::json([
            'tokens' => PersonalAccessToken::query()
                ->with('tokenable')
                ->orderBy('id')
                ->get()
                ->map(fn (PersonalAccessToken $token) => ApiTokens::describe($token))
                ->values()
                ->all(),
        ]));
    }
}
