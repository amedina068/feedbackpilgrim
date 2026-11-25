<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\InstanceStatus;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the health of the queue: the connection, the last heartbeat of the worker, the number of waiting jobs and the number of failed jobs. fresh is false when no worker ran a job in the last 5 minutes.')]
#[IsReadOnly]
class GetQueueHealthTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'get-queue-health';

    public function handle(Request $request, InstanceStatus $status): Response
    {
        return $this->audited($request, fn () => Response::json($status->queue()));
    }
}
