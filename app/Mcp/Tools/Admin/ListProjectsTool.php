<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Models\Client;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the projects of the instance with their subdomain, public URL, number of members and creation date.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'list-projects';

    public function handle(Request $request): Response
    {
        return $this->audited($request, fn () => Response::json([
            'projects' => Client::query()
                ->withCount('users')
                ->orderBy('id')
                ->get()
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'subdomain' => $client->slug,
                    'public_url' => $client->public_site,
                    'members' => (int) $client->users_count,
                    'created_at' => $client->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]));
    }
}
