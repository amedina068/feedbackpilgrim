<?php

namespace Tests\Feature\Extensions;

use App\Contracts\Entitlements;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entitlements::canCreateProject() limits the projects that a user creates
 * from the dashboard. The install command and the setup page are not limited.
 */
class ProjectLimitTest extends TestCase
{
    use RefreshDatabase;

    private function limit(): FakeEntitlements
    {
        $fake = new FakeEntitlements(canCreate: false);
        $this->app->instance(Entitlements::class, $fake);

        return $fake;
    }

    private function userWithProject(): User
    {
        $user = User::factory()->create();
        $user->clients()->attach(Client::factory()->create());

        return $user;
    }

    public function test_the_create_page_shows_the_limit_message_and_no_form(): void
    {
        $this->limit();
        $user = $this->userWithProject();

        $this->actingAs($user)
            ->get('/onboarding/project')
            ->assertOk()
            ->assertSee('Your account has 1 project. &lt;b&gt;Upgrade&lt;/b&gt; to add more.', false)
            ->assertDontSee('<b>Upgrade</b>', false)
            ->assertSee('href="/dashboard"', false)
            ->assertDontSee('action="/onboarding/project"', false)
            ->assertDontSee('Create project</button>', false);
    }

    public function test_the_store_request_is_refused_and_creates_no_project(): void
    {
        $this->limit();
        $user = $this->userWithProject();

        $this->actingAs($user)
            ->post('/onboarding/project', ['name' => 'Second', 'subdomain' => 'second'])
            ->assertForbidden();

        $this->assertSame(1, Client::query()->count());
        $this->assertFalse(Client::query()->where('slug', 'second')->exists());
    }

    public function test_a_limited_user_in_onboarding_creates_no_project(): void
    {
        $this->limit();
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)->get('/onboarding/project')->assertOk()->assertDontSee('action="/onboarding/project"', false);
        $this->actingAs($user)
            ->post('/onboarding/project', ['name' => 'Acme', 'subdomain' => 'acme'])
            ->assertForbidden();

        $this->assertSame(0, Client::query()->count());
        $this->assertSame(User::ONBOARDING_CLIENT, $user->refresh()->onboarding);
    }

    public function test_the_create_page_shows_the_form_when_the_user_can_create_a_project(): void
    {
        $user = $this->userWithProject();

        $this->actingAs($user)
            ->get('/onboarding/project')
            ->assertOk()
            ->assertSee('action="/onboarding/project"', false);
    }

    public function test_the_owner_creates_a_project_under_a_limit(): void
    {
        $fake = $this->limit();
        $owner = $this->userWithProject();
        $owner->forceFill(['is_owner' => true])->save();

        $this->actingAs($owner)->get('/onboarding/project')->assertOk()->assertSee('action="/onboarding/project"', false);
        $this->actingAs($owner)
            ->post('/onboarding/project', ['name' => 'Second', 'subdomain' => 'second'])
            ->assertRedirect('/dashboard');

        $this->assertTrue(Client::query()->where('slug', 'second')->exists());
        $this->assertSame([], $fake->askedFor);
    }

    public function test_the_install_command_creates_a_project_under_a_limit(): void
    {
        $this->limit();

        $this->artisan('fp:install', [
            '--name' => 'Jane Owner',
            '--email' => 'jane@example.com',
            '--password' => 'correct-horse-battery',
            '--client-name' => 'Acme',
            '--subdomain' => 'acme',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertTrue(Client::query()->where('slug', 'acme')->exists());
    }

    public function test_the_setup_page_creates_a_project_under_a_limit(): void
    {
        $this->limit();

        $this->post('/setup', [
            'token' => substr(hash_hmac('sha256', 'feedbackpilgrim-setup', config('app.key')), 0, 32),
            'name' => 'Jane Owner',
            'email' => 'jane@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'client_name' => 'Acme',
            'subdomain' => 'acme',
        ])->assertRedirect('/dashboard');

        $this->assertTrue(Client::query()->where('slug', 'acme')->exists());
    }
}
