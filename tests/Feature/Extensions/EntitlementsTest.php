<?php

namespace Tests\Feature\Extensions;

use App\Contracts\Entitlements;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\UnlimitedEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

/**
 * A hosted edition or a plugin can bind its own Entitlements. The core asks
 * that binding before it opens the dashboard and before it creates a project.
 */
class EntitlementsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProject(array $attributes = []): User
    {
        $client = Client::factory()->create();
        $user = User::factory()->create($attributes);
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    private function bindFromLaterProvider(Entitlements $entitlements): void
    {
        $this->app->register(new class($this->app, $entitlements) extends ServiceProvider
        {
            public function __construct($app, private Entitlements $entitlements)
            {
                parent::__construct($app);
            }

            public function register(): void
            {
                $this->app->singleton(Entitlements::class, fn () => $this->entitlements);
            }
        });
    }

    public function test_the_default_binding_allows_everything(): void
    {
        $user = User::factory()->create();
        $entitlements = $this->app->make(Entitlements::class);

        $this->assertInstanceOf(UnlimitedEntitlements::class, $entitlements);
        $this->assertSame($entitlements, $this->app->make(Entitlements::class));
        $this->assertNull($entitlements->accessRedirect($user));
        $this->assertTrue($entitlements->canCreateProject($user));
        $this->assertNotSame('', $entitlements->projectLimitMessage($user));
    }

    public function test_a_provider_that_registers_later_replaces_the_default_binding(): void
    {
        $fake = new FakeEntitlements;
        $this->bindFromLaterProvider($fake);

        $this->assertSame($fake, $this->app->make(Entitlements::class));
    }

    public function test_the_app_provider_keeps_a_binding_that_a_package_registered_first(): void
    {
        $fake = new FakeEntitlements;
        $this->app->forgetInstance(Entitlements::class);
        $this->app->singleton(Entitlements::class, fn () => $fake);

        (new AppServiceProvider($this->app))->register();

        $this->assertSame($fake, $this->app->make(Entitlements::class));
    }

    public function test_the_dashboard_opens_with_the_default_binding(): void
    {
        $user = $this->userWithProject();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_the_dashboard_sends_the_user_to_the_access_redirect_url(): void
    {
        $this->bindFromLaterProvider(new FakeEntitlements(redirect: '/billing-page'));
        $user = $this->userWithProject();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/billing-page');
        $this->actingAs($user)->get('/posts')->assertRedirect('/billing-page');
    }

    public function test_the_access_redirect_can_be_an_absolute_url(): void
    {
        $this->bindFromLaterProvider(new FakeEntitlements(redirect: 'https://accounts.example.com/pay'));
        $user = $this->userWithProject();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('https://accounts.example.com/pay');
    }

    public function test_onboarding_comes_before_the_access_redirect(): void
    {
        $this->bindFromLaterProvider(new FakeEntitlements(redirect: '/billing-page'));
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/onboarding/project');
    }

    public function test_a_new_user_with_no_project_still_completes_onboarding(): void
    {
        $fake = new FakeEntitlements(redirect: '/billing-page', canCreate: true);
        $this->bindFromLaterProvider($fake);
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)->get('/onboarding/project')->assertOk()->assertSee('Create project');
        $this->actingAs($user)
            ->post('/onboarding/project', ['name' => 'Acme Inc', 'subdomain' => 'acme'])
            ->assertRedirect('/dashboard');

        $this->assertTrue($user->clients()->where('slug', 'acme')->exists());
        $this->assertContains($user->id, $fake->askedFor);
        $this->actingAs($user->refresh())->get('/dashboard')->assertRedirect('/billing-page');
    }

    public function test_the_owner_is_never_redirected_and_opens_the_admin_area(): void
    {
        $fake = new FakeEntitlements(redirect: '/billing-page');
        $this->bindFromLaterProvider($fake);
        $owner = $this->userWithProject();
        $owner->forceFill(['is_owner' => true])->save();

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($owner)->get('/admin/users')->assertOk();
        $this->assertSame([], $fake->askedFor);
    }
}
