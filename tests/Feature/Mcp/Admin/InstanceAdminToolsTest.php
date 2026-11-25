<?php

namespace Tests\Feature\Mcp\Admin;

use App\Jobs\RecordQueueHeartbeat;
use App\Mcp\Servers\InstanceAdminServer;
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
use App\Models\Client;
use App\Models\User;
use App\Support\ApiTokens;
use App\Support\Registration;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class InstanceAdminToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'jane@example.com', 'onboarding' => User::ONBOARDING_COMPLETED]);
        $this->owner->forceFill(['is_owner' => true])->save();
        $this->client = Client::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
        $this->owner->clients()->attach($this->client);
    }

    /**
     * @param  class-string  $tool
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(string $tool, array $arguments = []): TestResponse
    {
        return InstanceAdminServer::tool($tool, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TestResponse $response): array
    {
        $property = new \ReflectionProperty($response, 'response');
        $text = $property->getValue($response)->toArray()['result']['content'][0]['text'];

        return json_decode($text, true);
    }

    public function test_the_stdio_server_is_registered(): void
    {
        $this->assertNotNull(Mcp::getLocalServer('fp-admin'));
    }

    public function test_each_tool_has_a_read_only_or_destructive_hint(): void
    {
        $tools = (new \ReflectionClass(InstanceAdminServer::class))->getDefaultProperties()['tools'];

        $this->assertCount(15, $tools);

        foreach ($tools as $toolClass) {
            $annotations = (new $toolClass)->annotations();

            $this->assertTrue(
                ($annotations['readOnlyHint'] ?? false) === true || array_key_exists('destructiveHint', $annotations),
                "{$toolClass} has no readOnlyHint or destructiveHint.",
            );
        }
    }

    public function test_get_instance_status(): void
    {
        $data = $this->payload($this->callTool(GetInstanceStatusTool::class)->assertOk());

        $this->assertTrue($data['database']['reachable']);
        $this->assertTrue($data['owner_exists']);
    }

    public function test_run_doctor(): void
    {
        $data = $this->payload($this->callTool(RunDoctorTool::class)->assertOk());

        $this->assertContains('database', array_column($data['checks'], 'id'));
    }

    public function test_list_users(): void
    {
        User::factory()->create(['email' => 'bob@example.com']);

        $data = $this->payload($this->callTool(ListUsersTool::class, ['search' => 'bob'])->assertOk());

        $this->assertSame(['bob@example.com'], array_column($data['users'], 'email'));
    }

    public function test_invite_user_creates_an_account_and_returns_a_login_link(): void
    {
        $data = $this->payload($this->callTool(InviteUserTool::class, [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'project' => 'acme',
        ])->assertOk());

        $bob = User::query()->where('email', 'bob@example.com')->firstOrFail();
        $this->assertFalse($bob->isOwner());
        $this->assertTrue($bob->clients()->whereKey($this->client->id)->exists());
        $this->assertStringContainsString('/login/link/'.$bob->id, $data['login_url']);

        $this->get($data['login_url'])->assertRedirect(route('profile.edit'));
        $this->assertAuthenticatedAs($bob);
    }

    public function test_invite_user_refuses_an_existing_email(): void
    {
        $this->callTool(InviteUserTool::class, ['name' => 'Jane', 'email' => 'jane@example.com'])->assertHasErrors();

        $this->assertSame(1, User::query()->count());
    }

    public function test_list_api_tokens_shows_no_secrets(): void
    {
        ApiTokens::createProductToken($this->owner, $this->client, 'agent');

        $response = $this->callTool(ListApiTokensTool::class)->assertOk();
        $data = $this->payload($response);

        $this->assertSame(['agent'], array_column($data['tokens'], 'name'));
        $this->assertArrayNotHasKey('token', $data['tokens'][0]);
    }

    public function test_create_api_token_creates_a_product_token(): void
    {
        $data = $this->payload($this->callTool(CreateApiTokenTool::class, [
            'user_email' => 'jane@example.com',
            'name' => 'zapier',
            'abilities' => ['api'],
            'expires_in_days' => 30,
        ])->assertOk());

        $this->assertSame(['api'], $data['token']['abilities']);
        $this->assertMatchesRegularExpression('/^\d+\|\S+$/', $data['token']['plain_text']);
        $this->assertSame('acme', $data['token']['project']);
    }

    public function test_create_api_token_never_creates_an_admin_or_wildcard_token(): void
    {
        foreach ([['instance:admin'], ['*'], ['api', 'instance:admin']] as $abilities) {
            $this->callTool(CreateApiTokenTool::class, [
                'user_email' => 'jane@example.com',
                'name' => 'bad',
                'abilities' => $abilities,
            ])->assertHasErrors();
        }

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_revoke_api_token_previews_first_and_needs_confirm(): void
    {
        $token = ApiTokens::createProductToken($this->owner, $this->client, 'old')->accessToken;

        $preview = $this->payload($this->callTool(RevokeApiTokenTool::class, ['id' => $token->id])->assertOk());
        $this->assertTrue($preview['confirm_required']);
        $this->assertSame('old', $preview['preview']['name']);
        $this->assertNotNull(PersonalAccessToken::query()->find($token->id));

        $done = $this->payload($this->callTool(RevokeApiTokenTool::class, ['id' => $token->id, 'confirm' => true])->assertOk());
        $this->assertSame('revoked', $done['status']);
        $this->assertNull(PersonalAccessToken::query()->find($token->id));
    }

    public function test_revoke_api_token_reports_an_unknown_id(): void
    {
        $this->callTool(RevokeApiTokenTool::class, ['id' => 999, 'confirm' => true])->assertHasErrors();
    }

    public function test_get_settings(): void
    {
        $data = $this->payload($this->callTool(GetSettingsTool::class)->assertOk());

        $this->assertSame(Registration::CLOSED, $data['settings']['registration_mode']['value']);
        $this->assertSame(Registration::MODES, $data['settings']['registration_mode']['allowed']);
    }

    public function test_set_setting_previews_first_and_then_changes_the_value(): void
    {
        $preview = $this->payload($this->callTool(SetSettingTool::class, ['key' => 'registration_mode', 'value' => 'open'])->assertOk());
        $this->assertTrue($preview['confirm_required']);
        $this->assertSame(Registration::CLOSED, Registration::mode());

        $this->callTool(SetSettingTool::class, ['key' => 'registration_mode', 'value' => 'open', 'confirm' => true])->assertOk();
        $this->assertSame(Registration::OPEN, Registration::mode());
    }

    public function test_set_setting_refuses_keys_outside_the_allowlist_and_bad_values(): void
    {
        $this->callTool(SetSettingTool::class, ['key' => 'app_key', 'value' => 'x', 'confirm' => true])->assertHasErrors();
        $this->callTool(SetSettingTool::class, ['key' => 'registration_mode', 'value' => 'maybe', 'confirm' => true])->assertHasErrors();

        $this->assertSame(Registration::CLOSED, Registration::mode());
    }

    public function test_get_queue_health(): void
    {
        $data = $this->payload($this->callTool(GetQueueHealthTool::class)->assertOk());

        $this->assertSame('sync', $data['connection']);
        $this->assertSame(0, $data['failed_jobs']);
    }

    public function test_retry_failed_jobs_previews_first_and_then_retries(): void
    {
        Queue::fake();
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => RecordQueueHeartbeat::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => RecordQueueHeartbeat::class, 'command' => serialize(new RecordQueueHeartbeat)],
            ]),
            'exception' => "RuntimeException: Boom\n#0 stack",
            'failed_at' => now(),
        ]);

        $preview = $this->payload($this->callTool(RetryFailedJobsTool::class)->assertOk());
        $this->assertTrue($preview['confirm_required']);
        $this->assertSame(RecordQueueHeartbeat::class, $preview['preview']['jobs'][0]['job']);
        $this->assertSame('RuntimeException: Boom', $preview['preview']['jobs'][0]['error']);
        $this->assertSame(1, DB::table('failed_jobs')->count());

        $this->callTool(RetryFailedJobsTool::class, ['confirm' => true])->assertOk();
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_read_recent_errors_returns_errors_with_secrets_redacted(): void
    {
        $directory = sys_get_temp_dir().'/fp-logs-'.uniqid();
        mkdir($directory);
        config(['logging.channels.single.path' => $directory.'/laravel.log']);

        $key = (string) config('app.key');
        file_put_contents($directory.'/laravel.log', implode("\n", [
            '[2026-11-25 10:00:00] testing.INFO: All good',
            '[2026-11-25 10:01:00] testing.ERROR: Token 12|abcdefghijklmnopqrstuvwxyz0123456789ABCD failed with key '.$key.' and password=hunter22 {"exception":"x"}',
            '#0 /var/www/html/app/Foo.php(10)',
            '[2026-11-25 10:02:00] testing.CRITICAL: Authorization: Bearer secret-value-here',
        ]));

        try {
            $response = $this->callTool(ReadRecentErrorsTool::class)->assertOk();
            $data = $this->payload($response);
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }

        $this->assertCount(2, $data['errors']);
        $text = json_encode($data);
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz0123456789ABCD', $text);
        $this->assertStringNotContainsString($key, $text);
        $this->assertStringNotContainsString('hunter22', $text);
        $this->assertStringNotContainsString('secret-value-here', $text);
        $this->assertStringContainsString('[REDACTED]', $text);
    }

    public function test_send_test_email_goes_to_the_owner_by_default(): void
    {
        Mail::fake();

        $data = $this->payload($this->callTool(SendTestEmailTool::class)->assertOk());

        $this->assertSame('jane@example.com', $data['to']);
    }

    public function test_send_test_email_sends_a_real_message(): void
    {
        config(['mail.default' => 'array']);

        $this->callTool(SendTestEmailTool::class, ['to' => 'ops@example.com'])->assertOk();

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('ops@example.com', $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_list_projects(): void
    {
        $data = $this->payload($this->callTool(ListProjectsTool::class)->assertOk());

        $this->assertSame(['acme'], array_column($data['projects'], 'subdomain'));
        $this->assertSame(1, $data['projects'][0]['members']);
    }

    public function test_create_project_for_the_owner(): void
    {
        $data = $this->payload($this->callTool(CreateProjectTool::class, ['name' => 'Beta', 'subdomain' => 'beta'])->assertOk());

        $this->assertSame('beta', $data['project']['subdomain']);
        $this->assertTrue($this->owner->clients()->where('slug', 'beta')->exists());
    }

    public function test_create_project_refuses_a_taken_or_reserved_subdomain(): void
    {
        $this->callTool(CreateProjectTool::class, ['name' => 'Again', 'subdomain' => 'acme'])->assertHasErrors();
        $this->callTool(CreateProjectTool::class, ['name' => 'Admin', 'subdomain' => 'admin'])->assertHasErrors();

        $this->assertSame(1, Client::query()->count());
    }

    public function test_each_call_is_audited_without_secrets(): void
    {
        $directory = sys_get_temp_dir().'/fp-audit-'.uniqid();
        config(['logging.channels.mcp-admin.path' => $directory.'/mcp-admin.log']);

        try {
            $this->callTool(CreateApiTokenTool::class, ['user_email' => 'jane@example.com', 'name' => 'zapier']);
            $this->callTool(ListUsersTool::class);
            $this->callTool(InviteUserTool::class, ['name' => 'Jane', 'email' => 'jane@example.com']);

            $lines = collect(glob($directory.'/mcp-admin*.log'))
                ->flatMap(fn (string $file) => file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
                ->map(fn (string $line) => json_decode($line, true)['context'])
                ->values();
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }

        $this->assertSame(['create-api-token', 'list-users', 'invite-user'], $lines->pluck('tool')->all());
        $this->assertSame('stdio', $lines[0]['transport']);
        $this->assertNull($lines[0]['user']);
        $this->assertTrue($lines[0]['ok']);
        $this->assertFalse($lines[2]['ok']);
        $this->assertSame('zapier', $lines[0]['arguments']['name']);
        $this->assertStringNotContainsString('|', $lines->toJson());
    }
}
