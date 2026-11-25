<?php

namespace Tests\Feature\Agent;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `--json` on the commands that existed before the agent contract.
 */
class ExistingCommandsJsonTest extends TestCase
{
    use RefreshDatabase;
    use RunsJsonCommands;

    public function test_setup_url_returns_the_url_while_setup_is_needed(): void
    {
        $result = $this->runJson('fp:setup-url');

        $this->assertSame(0, $result['exit']);
        $this->assertTrue($result['data']['setup_needed']);
        $this->assertStringContainsString('/setup?token=', $result['data']['url']);
    }

    public function test_setup_url_returns_no_url_after_an_account_exists(): void
    {
        User::factory()->create();

        $data = $this->runJson('fp:setup-url')['data'];

        $this->assertFalse($data['setup_needed']);
        $this->assertNull($data['url']);
    }

    public function test_user_list_returns_the_users(): void
    {
        $owner = User::factory()->create(['name' => 'Jane Owner', 'email' => 'jane@example.com']);
        $owner->forceFill(['is_owner' => true])->save();
        $owner->clients()->attach(Client::factory()->create(['name' => 'Acme']));
        User::factory()->create(['email' => 'bob@example.com']);

        $result = $this->runJson('fp:user:list');

        $this->assertSame(0, $result['exit']);
        $this->assertCount(2, $result['data']['users']);
        $jane = $result['data']['users'][0];
        $this->assertSame(['id', 'name', 'email', 'projects', 'owner', 'status', 'created_at'], array_keys($jane));
        $this->assertSame('jane@example.com', $jane['email']);
        $this->assertSame(['Acme'], $jane['projects']);
        $this->assertTrue($jane['owner']);
        $this->assertSame('active', $jane['status']);

        $filtered = $this->runJson('fp:user:list', ['--search' => 'bob'])['data']['users'];
        $this->assertSame(['bob@example.com'], array_column($filtered, 'email'));
    }

    public function test_user_password_generates_a_password_and_returns_it_one_time(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $result = $this->runJson('fp:user:password', ['email' => 'jane@example.com', '--generate' => true]);

        $this->assertSame(0, $result['exit']);
        $this->assertSame('jane@example.com', $result['data']['email']);
        $this->assertTrue(Hash::check($result['data']['password'], $user->fresh()->password));
        $this->assertArrayHasKey('sessions', $result['data']);
    }

    public function test_user_password_with_a_given_password_does_not_echo_it(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $data = $this->runJson('fp:user:password', ['email' => 'jane@example.com', '--password' => 'a-long-new-password'])['data'];

        $this->assertNull($data['password']);
    }

    public function test_user_password_errors_are_bad_input(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $result = $this->runJson('fp:user:password', ['email' => 'nobody@example.com', '--generate' => true]);
        $this->assertSame(2, $result['exit']);
        $this->assertSame('user_not_found', $result['errors'][0]['code']);

        $result = $this->runJson('fp:user:password', ['email' => 'jane@example.com', '--password' => 'short']);
        $this->assertSame(2, $result['exit']);
        $this->assertSame('invalid_password', $result['errors'][0]['code']);

        $result = $this->runJson('fp:user:password', ['email' => 'jane@example.com']);
        $this->assertSame(2, $result['exit']);
        $this->assertSame('missing_option', $result['errors'][0]['code']);
    }

    public function test_weekly_returns_the_number_of_reports(): void
    {
        Client::factory()->count(2)->create();

        $result = $this->runJson('fp:weekly');

        $this->assertSame(0, $result['exit']);
        $this->assertSame(2, $result['data']['reports']);
        $this->assertSame(now()->subWeek()->startOfWeek()->toDateString(), $result['data']['week_start']);
    }
}
