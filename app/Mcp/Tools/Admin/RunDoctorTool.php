<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\InstanceDoctor;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Run the health checks of the instance. Returns a list of {id, status: ok|warn|fail, message, fix}. The tool changes nothing. To run the safe fixes, use "php artisan fp:doctor --fix" on the server.')]
#[IsReadOnly]
class RunDoctorTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'run-doctor';

    public function handle(Request $request, InstanceDoctor $doctor): Response
    {
        return $this->audited($request, function () use ($doctor) {
            $checks = $doctor->run();

            return Response::json(['healthy' => ! InstanceDoctor::hasFailure($checks), 'checks' => $checks]);
        });
    }
}
