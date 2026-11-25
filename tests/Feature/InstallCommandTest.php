<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * fp:install prepares a new self-hosted instance. Registration is off by
 * default, so this command creates the owner, the first client and the admin
 * contact of the owner.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string|bool>
     */
    private function installOptions(array $overrides = []): array
    {
        return array_merge([
            '--name' => 'Jane Owner',
            '--email' => 'jane@example.com',
            '--password' => 'correct-horse-battery',
            '--client-name' => 'Acme',
            '--subdomain' => 'acme',
            '--no-interaction' => true,
        ], $overrides);
    }

    public function test_install_creates_the_owner_the_client_and_the_admin_contact(): void
    {
        $this->artisan('fp:install', $this->installOptions())->assertSuccessful();

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane Owner', $user->name);
        $this->assertSame(User::ONBOARDING_COMPLETED, $user->onboarding);
        $this->assertTrue($user->hasAccess());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));

        $client = Client::query()->where('slug', 'acme')->firstOrFail();
        $this->assertSame('Acme', $client->name);
        $this->assertTrue($user->clients()->whereKey($client->id)->exists());

        $contact = Contact::query()->where('user_id', $user->id)->where('client_id', $client->id)->firstOrFail();
        $this->assertSame('admin', json_decode($contact->metadata, true)['role']);
    }

    public function test_install_prints_the_dashboard_and_public_site_urls(): void
    {
        $domain = config('app.domain');

        $this->artisan('fp:install', $this->installOptions())
            ->expectsOutputToContain('//'.$domain.'/login')
            ->expectsOutputToContain('//acme.'.$domain)
            ->assertSuccessful();
    }

    public function test_install_reports_a_conflict_when_another_user_exists(): void
    {
        User::factory()->create();

        $this->artisan('fp:install', $this->installOptions())->assertExitCode(3);

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_install_rejects_a_short_password(): void
    {
        $this->artisan('fp:install', $this->installOptions(['--password' => 'short']))->assertExitCode(2);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_install_rejects_an_invalid_subdomain(): void
    {
        $this->artisan('fp:install', $this->installOptions(['--subdomain' => 'Not A Subdomain']))->assertExitCode(2);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_install_rejects_an_invalid_email(): void
    {
        $this->artisan('fp:install', $this->installOptions(['--email' => 'not-an-email']))->assertExitCode(2);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_install_makes_the_new_user_the_owner(): void
    {
        $this->artisan('fp:install', $this->installOptions())->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'jane@example.com')->firstOrFail()->isOwner());
    }

    public function test_prepare_creates_no_account_and_succeeds_on_a_used_instance(): void
    {
        $this->artisan('fp:install', ['--prepare' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);

        User::factory()->create();

        $this->artisan('fp:install', ['--prepare' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_promote_makes_an_existing_user_the_owner(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('fp:install', ['--promote' => 'operator@example.com', '--no-interaction' => true])
            ->expectsOutputToContain('operator@example.com is now an owner')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isOwner());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_promote_refuses_an_unknown_email(): void
    {
        $this->artisan('fp:install', ['--promote' => 'nobody@example.com', '--no-interaction' => true])
            ->expectsOutputToContain('No user has the email nobody@example.com.')
            ->assertFailed();
    }

    public function test_install_asks_for_missing_values(): void
    {
        $this->artisan('fp:install')
            ->expectsQuestion('Owner name', 'Jane Owner')
            ->expectsQuestion('Owner email', 'jane@example.com')
            ->expectsQuestion('Project name', 'Acme')
            ->expectsQuestion('Project subdomain', 'acme')
            ->assertSuccessful();

        $this->assertDatabaseHas('clients', ['slug' => 'acme']);
    }
}
