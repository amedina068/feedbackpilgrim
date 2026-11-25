<?php

namespace Tests\Feature\Extensions;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Support\DashboardBanners;
use App\Support\DashboardNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A hosted edition or a plugin adds links to the dashboard navigation with
 * DashboardNavigation, and does not change the core views.
 */
class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Start each test with empty registries. An installed package, for
     * example a hosted edition, can add its own banners and links.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DashboardBanners::class, new DashboardBanners);
        $this->app->instance(DashboardNavigation::class, new DashboardNavigation);
    }

    private function actingAdmin(): User
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    private function navigation(): DashboardNavigation
    {
        return $this->app->make(DashboardNavigation::class);
    }

    public function test_the_registry_is_a_singleton(): void
    {
        $this->assertSame($this->navigation(), $this->navigation());
    }

    public function test_items_returns_only_the_visible_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->navigation()
            ->add('Billing', '/billing')
            ->add('Usage', '/usage', fn (User $viewer): bool => $viewer->is($user));

        $this->assertSame(
            [['label' => 'Billing', 'url' => '/billing'], ['label' => 'Usage', 'url' => '/usage']],
            $this->navigation()->items($user),
        );
        $this->assertSame([['label' => 'Billing', 'url' => '/billing']], $this->navigation()->items($other));
    }

    public function test_the_dashboard_shows_no_extra_link_when_no_item_is_added(): void
    {
        $html = $this->actingAs($this->actingAdmin())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('/billing', $html);
    }

    public function test_the_dashboard_shows_an_added_item_in_the_sidebar_and_the_mobile_menu(): void
    {
        $this->navigation()->add('Billing <now>', '/billing');

        $html = $this->actingAs($this->actingAdmin())->get('/dashboard')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'href="/billing"'));
        $this->assertSame(2, substr_count($html, 'Billing &lt;now&gt;'));
        $this->assertStringNotContainsString('Billing <now>', $html);
    }

    public function test_the_dashboard_hides_an_item_that_is_not_visible_to_the_user(): void
    {
        $this->navigation()->add('Billing', '/billing', fn (User $user): bool => false);

        $this->actingAs($this->actingAdmin())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="/billing"', false);
    }

    public function test_the_item_of_the_current_page_is_marked_as_current(): void
    {
        $this->navigation()->add('Settings copy', '/settings/project');

        $html = $this->actingAs($this->actingAdmin())->get('/settings/project')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="/settings/project"\s+aria-current="page"#', $html);
    }
}
