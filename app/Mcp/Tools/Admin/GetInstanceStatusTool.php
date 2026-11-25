<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\InstanceStatus;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the state of the instance: version, app URL, database driver and pending migrations, queue and scheduler heartbeats, storage link, mail driver, MCP settings and whether an owner exists. The same data as "php artisan fp:status --json".')]
#[IsReadOnly]
class GetInstanceStatusTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'get-instance-status';

    public function handle(Request $request, InstanceStatus $status): Response
    {
        return $this->audited($request, fn () => Response::json($status->collect()));
    }
}
