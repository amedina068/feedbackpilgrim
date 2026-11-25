<?php

namespace Tests\Feature\Agent;

use App\Models\User;
use App\Support\Heartbeats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusCommandTest extends TestCase
{
    use RefreshDatabase;
    use RunsJsonCommands;

    public function test_status_returns_the_state_of_the_instance(): void
    {
        $result = $this->runJson('fp:status');

        $this->assertSame(0, $result['exit']);
        $data = $result['data'];

        $this->assertSame([
            'version', 'environment', 'app_url', 'database', 'queue', 'scheduler',
            'storage_link', 'mail', 'mcp', 'owner_exists', 'setup_url',
        ], array_keys($data));

        $this->assertSame('sqlite', $data['database']['driver']);
        $this->assertTrue($data['database']['reachable']);
        $this->assertSame(0, $data['database']['pending_migrations']);
        $this->assertSame('sync', $data['queue']['connection']);
        $this->assertSame('array', $data['mail']['mailer']);
        $this->assertTrue($data['mcp']['enabled']);
        $this->assertSame('http://localhost/mcp', $data['mcp']['url']);
        $this->assertFalse($data['mcp']['admin_http_enabled']);
        $this->assertFalse($data['owner_exists']);
        $this->assertStringContainsString('/setup?token=', $data['setup_url']);
    }

    public function test_status_reports_the_owner_and_the_heartbeats(): void
    {
        User::factory()->create()->forceFill(['is_owner' => true])->save();
        Heartbeats::recordScheduler();
        Heartbeats::recordQueue();

        $data = $this->runJson('fp:status')['data'];

        $this->assertTrue($data['owner_exists']);
        $this->assertNull($data['setup_url']);
        $this->assertTrue($data['scheduler']['fresh']);
        $this->assertTrue($data['queue']['fresh']);
    }

    public function test_a_stale_heartbeat_is_not_fresh(): void
    {
        Heartbeats::recordScheduler();
        $this->travel(Heartbeats::STALE_MINUTES + 1)->minutes();

        $this->assertFalse($this->runJson('fp:status')['data']['scheduler']['fresh']);
    }

    public function test_status_fails_when_the_database_is_not_reachable(): void
    {
        $default = config('database.default');
        config([
            'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/fp/database.sqlite', 'prefix' => ''],
            'database.default' => 'broken',
        ]);

        try {
            $result = $this->runJson('fp:status');
        } finally {
            config(['database.default' => $default]);
        }

        $this->assertSame(1, $result['exit']);
        $this->assertFalse($result['data']['database']['reachable']);
        $this->assertSame('db_unreachable', $result['errors'][0]['code']);
    }

    public function test_status_without_json_prints_a_table(): void
    {
        $this->artisan('fp:status')
            ->expectsOutputToContain('sqlite, reachable')
            ->expectsOutputToContain('Pending migrations')
            ->assertSuccessful();
    }
}
