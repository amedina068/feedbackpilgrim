<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\Registration;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the settings of the instance. "settings" holds the values that set-setting can change. "environment" holds values from the .env file, which only the server operator can change.')]
#[IsReadOnly]
class GetSettingsTool extends Tool
{
    use AuditsAdminCalls;

    protected string $name = 'get-settings';

    public function handle(Request $request): Response
    {
        return $this->audited($request, fn () => Response::json([
            'settings' => [
                'registration_mode' => [
                    'value' => Registration::mode(),
                    'default' => Registration::defaultMode(),
                    'allowed' => Registration::MODES,
                    'description' => 'open: anyone can sign up and create projects. closed: nobody can sign up.',
                ],
            ],
            'environment' => [
                'app_url' => (string) config('app.url'),
                'mailer' => (string) config('mail.default'),
                'mail_from' => config('mail.from.address'),
                'queue_connection' => (string) config('queue.default'),
                'cache_store' => (string) config('cache.default'),
                'mcp_admin_http_enabled' => (bool) config('mcp.admin.http_enabled'),
            ],
        ]));
    }
}
