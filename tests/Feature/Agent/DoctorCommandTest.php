<?php

namespace Tests\Feature\Agent;

use App\Models\Client;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;
    use RunsJsonCommands;

    private ?string $publicDirectory = null;

    protected function tearDown(): void
    {
        if ($this->publicDirectory !== null) {
            (new Filesystem)->deleteDirectory($this->publicDirectory);
        }

        parent::tearDown();
    }

    /**
     * Point the storage link at a temporary public folder.
     */
    private function useTemporaryPublicFolder(): string
    {
        $this->publicDirectory = sys_get_temp_dir().'/fp-doctor-'.uniqid();
        mkdir($this->publicDirectory);
        config(['filesystems.links' => [$this->publicDirectory.'/storage' => storage_path('app/public')]]);

        return $this->publicDirectory.'/storage';
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return array<string, mixed>
     */
    private function check(array $checks, string $id): array
    {
        foreach ($checks as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }

        $this->fail("The doctor has no check \"{$id}\".");
    }

    public function test_doctor_returns_a_list_of_checks(): void
    {
        $this->useTemporaryPublicFolder();

        $result = $this->runJson('fp:doctor');

        $this->assertSame(0, $result['exit']);

        $ids = array_column($result['data']['checks'], 'id');
        foreach (['app_key', 'debug_mode', 'database', 'migrations', 'storage_writable', 'storage_link', 'cache', 'owner', 'mail', 'queue_worker', 'scheduler', 'failed_jobs', 'legacy_tokens'] as $id) {
            $this->assertContains($id, $ids);
        }

        foreach ($result['data']['checks'] as $check) {
            $this->assertSame(['id', 'status', 'message', 'fix', 'fixed'], array_keys($check));
            $this->assertContains($check['status'], ['ok', 'warn', 'fail']);
        }

        $this->assertSame('ok', $this->check($result['data']['checks'], 'database')['status']);
        $this->assertSame('warn', $this->check($result['data']['checks'], 'owner')['status']);
        $this->assertSame('warn', $this->check($result['data']['checks'], 'storage_link')['status']);
    }

    public function test_doctor_warns_about_legacy_tokens(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        $user->createToken('old', ['*']);

        $check = $this->check($this->runJson('fp:doctor')['data']['checks'], 'legacy_tokens');

        $this->assertSame('warn', $check['status']);
        $this->assertStringContainsString('fp:token:create', $check['fix']);
    }

    public function test_doctor_fails_without_an_app_key(): void
    {
        config(['app.key' => '']);

        $result = $this->runJson('fp:doctor');

        $this->assertSame(1, $result['exit']);
        $this->assertSame('fail', $this->check($result['data']['checks'], 'app_key')['status']);
        $this->assertSame('app_key', $result['errors'][0]['code']);
    }

    public function test_fix_creates_the_storage_link_and_nothing_else(): void
    {
        $link = $this->useTemporaryPublicFolder();

        $result = $this->runJson('fp:doctor', ['--fix' => true]);

        $this->assertFileExists($link);
        $check = $this->check($result['data']['checks'], 'storage_link');
        $this->assertSame('ok', $check['status']);
        $this->assertTrue($check['fixed']);
        $this->assertFalse($this->check($result['data']['checks'], 'owner')['fixed']);
    }

    public function test_doctor_without_json_prints_each_check(): void
    {
        $this->artisan('fp:doctor')
            ->expectsOutputToContain('database')
            ->assertSuccessful();
    }
}
