<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * fp:user:password sets a new password without email, for an instance with
 * no SMTP. Feedback Pilgrim has no two-factor authentication, so the command
 * has no option to reset it.
 */
class UserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_the_password_from_the_prompt(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);

        $this->artisan('fp:user:password', ['email' => 'lost@example.com'])
            ->expectsQuestion('New password', 'a-long-password-1')
            ->expectsQuestion('Type the new password again', 'a-long-password-1')
            ->expectsOutputToContain('The password of lost@example.com changed.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('a-long-password-1', $user->fresh()->password));
    }

    public function test_it_refuses_two_different_passwords_from_the_prompt(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);
        $before = $user->password;

        $this->artisan('fp:user:password', ['email' => 'lost@example.com'])
            ->expectsQuestion('New password', 'a-long-password-1')
            ->expectsQuestion('Type the new password again', 'a-long-password-2')
            ->expectsOutputToContain('The two passwords are not the same.')
            ->assertExitCode(2);

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_it_sets_the_password_from_the_option(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'a-long-password-1',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('a-long-password-1', $user->fresh()->password));
    }

    public function test_the_new_password_works_on_the_login_form(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'a-long-password-1',
        ])->assertSuccessful();

        $this->post('/login', ['email' => 'lost@example.com', 'password' => 'a-long-password-1'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_it_generates_a_password_that_authenticates_the_user(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);

        $exitCode = Artisan::call('fp:user:password', [
            'email' => 'lost@example.com',
            '--generate' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, preg_match('/New password: (\S+)/', Artisan::output(), $matches));
        $this->assertGreaterThanOrEqual(20, strlen($matches[1]));
        $this->assertTrue(Hash::check($matches[1], $user->fresh()->password));
    }

    public function test_it_refuses_a_password_that_breaks_the_rules(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);
        $before = $user->password;

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'short',
        ])->expectsOutputToContain('at least 8 characters')->assertExitCode(2);

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_it_refuses_an_unknown_email(): void
    {
        $this->artisan('fp:user:password', [
            'email' => 'nobody@example.com',
            '--password' => 'a-long-password-1',
        ])->expectsOutputToContain('No user has the email nobody@example.com.')->assertExitCode(2);
    }

    public function test_it_refuses_the_password_and_the_generate_options_together(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com']);
        $before = $user->password;

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'a-long-password-1',
            '--generate' => true,
        ])->expectsOutputToContain('Use --password or --generate, not the two together.')->assertExitCode(2);

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_it_needs_an_option_when_it_cannot_prompt(): void
    {
        User::factory()->create(['email' => 'lost@example.com']);

        $this->artisan('fp:user:password', ['email' => 'lost@example.com', '--no-interaction' => true])
            ->expectsOutputToContain('Give --password or --generate')
            ->assertExitCode(2);
    }

    public function test_it_rotates_the_remember_token(): void
    {
        $user = User::factory()->create(['email' => 'lost@example.com', 'remember_token' => 'old-token']);

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'a-long-password-1',
        ])->expectsOutputToContain('"Remember me" logins ended')->assertSuccessful();

        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_it_ends_the_database_sessions_of_the_user(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['email' => 'lost@example.com']);
        $other = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'c', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'a-long-password-1',
        ])->expectsOutputToContain('Ended 2 web sessions')->assertSuccessful();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
    }

    public function test_it_explains_the_session_effect_for_other_drivers(): void
    {
        config(['session.driver' => 'redis']);
        User::factory()->create(['email' => 'lost@example.com']);

        $this->artisan('fp:user:password', [
            'email' => 'lost@example.com',
            '--password' => 'a-long-password-1',
        ])->expectsOutputToContain('The "redis" session driver')->assertSuccessful();
    }
}
