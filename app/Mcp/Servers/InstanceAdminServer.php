<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Admin\CreateApiTokenTool;
use App\Mcp\Tools\Admin\CreateProjectTool;
use App\Mcp\Tools\Admin\GetInstanceStatusTool;
use App\Mcp\Tools\Admin\GetQueueHealthTool;
use App\Mcp\Tools\Admin\GetSettingsTool;
use App\Mcp\Tools\Admin\InviteUserTool;
use App\Mcp\Tools\Admin\ListApiTokensTool;
use App\Mcp\Tools\Admin\ListProjectsTool;
use App\Mcp\Tools\Admin\ListUsersTool;
use App\Mcp\Tools\Admin\ReadRecentErrorsTool;
use App\Mcp\Tools\Admin\RetryFailedJobsTool;
use App\Mcp\Tools\Admin\RevokeApiTokenTool;
use App\Mcp\Tools\Admin\RunDoctorTool;
use App\Mcp\Tools\Admin\SendTestEmailTool;
use App\Mcp\Tools\Admin\SetSettingTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * The instance-admin MCP server. It operates the whole instance, not one
 * project, so it is separate from {@see PublicServer}.
 *
 * It runs over stdio (`php artisan mcp:start fp-admin`). With
 * MCP_ADMIN_ENABLED=true it also answers at /mcp/admin for a short-lived
 * owner token. These actions stay CLI-only and have no tool: create the owner,
 * promote or demote a user, rotate keys, revoke all tokens, delete data.
 */
#[Name('Feedback Pilgrim instance admin')]
#[Version('1.0.0')]
#[Instructions(InstanceAdminServer::INSTRUCTIONS)]
class InstanceAdminServer extends Server
{
    public const INSTRUCTIONS = <<<'MARKDOWN'
This server operates one Feedback Pilgrim instance: its health, users, projects, API
tokens, settings, queue and mail.

Start with get-instance-status and run-doctor. Each check of run-doctor has a fix.

A tool with destructiveHint returns a preview first and changes nothing. Show the
preview to the person. Call the tool again with the same arguments and confirm: true
only after the person agrees.

create-api-token makes product tokens only (mcp:read, mcp:write, api). It never makes
an admin token. The server writes each call to the mcp-admin audit log.

Some actions stay on the command line: create the owner, promote or demote a user,
rotate keys, revoke all tokens, and delete data.
MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        GetInstanceStatusTool::class,
        RunDoctorTool::class,
        ListUsersTool::class,
        InviteUserTool::class,
        ListProjectsTool::class,
        CreateProjectTool::class,
        ListApiTokensTool::class,
        CreateApiTokenTool::class,
        RevokeApiTokenTool::class,
        GetSettingsTool::class,
        SetSettingTool::class,
        GetQueueHealthTool::class,
        RetryFailedJobsTool::class,
        ReadRecentErrorsTool::class,
        SendTestEmailTool::class,
    ];
}
