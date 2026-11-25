<?php

namespace Tests\Feature\Extensions;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Support\DashboardBanners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use UnexpectedValueException;

/**
 * A hosted edition or a plugin shows one-line notices at the top of the
 * dashboard with DashboardBanners.
 */
class DashboardBannersTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    private function banners(): DashboardBanners
    {
        return $this->app->make(DashboardBanners::class);
    }

    public function test_the_registry_is_a_singleton(): void
    {
        $this->assertSame($this->banners(), $this->banners());
    }

    public function test_items_returns_the_non_null_banners_for_the_user(): void
    {
        $user = User::factory()->create();

        $this->banners()
            ->add(fn (User $viewer): ?array => null)
            ->add(fn (User $viewer): array => ['message' => "Hello {$viewer->id}"])
            ->add(fn (User $viewer): array => ['message' => 'Read this.', 'url' => '/notices', 'link' => 'Open']);

        $this->assertSame([
            ['message' => "Hello {$user->id}", 'url' => null, 'link' => null],
            ['message' => 'Read this.', 'url' => '/notices', 'link' => 'Open'],
        ], $this->banners()->items($user));
    }

    public function test_a_banner_with_a_url_and_no_link_text_gets_a_default_link_text(): void
    {
        $this->banners()->add(fn (User $viewer): array => ['message' => 'Read this.', 'url' => '/notices']);

        $this->assertSame('Details', $this->banners()->items(User::factory()->create())[0]['link']);
    }

    public function test_a_banner_without_a_message_is_an_error(): void
    {
        $this->banners()->add(fn (User $viewer): array => ['url' => '/notices']);

        $this->expectException(UnexpectedValueException::class);

        $this->banners()->items(User::factory()->create());
    }

    public function test_a_banner_url_with_a_script_scheme_is_an_error(): void
    {
        $this->banners()->add(fn (User $viewer): array => ['message' => 'Hi', 'url' => 'javascript:alert(1)']);

        $this->expectException(UnexpectedValueException::class);

        $this->banners()->items(User::factory()->create());
    }

    public function test_the_dashboard_shows_no_banner_markup_without_resolvers(): void
    {
        $this->actingAs($this->actingAdmin())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('aria-label="Notices"', false);
    }

    public function test_the_dashboard_shows_no_banner_markup_when_every_resolver_returns_null(): void
    {
        $this->banners()->add(fn (User $viewer): ?array => null);

        $this->actingAs($this->actingAdmin())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('aria-label="Notices"', false);
    }

    public function test_the_dashboard_shows_the_banners_escaped(): void
    {
        $this->banners()
            ->add(fn (User $viewer): array => ['message' => 'Your trial <script>x()</script> ends in 3 days.', 'url' => '/choose?a=1&b=2', 'link' => 'Choose <now>'])
            ->add(fn (User $viewer): ?array => null)
            ->add(fn (User $viewer): array => ['message' => 'Second notice.']);

        $html = $this->actingAs($this->actingAdmin())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Notices"', $html);
        $this->assertStringContainsString('Your trial &lt;script&gt;x()&lt;/script&gt; ends in 3 days.', $html);
        $this->assertStringNotContainsString('<script>x()</script>', $html);
        $this->assertStringContainsString('href="/choose?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('Choose &lt;now&gt;', $html);
        $this->assertStringContainsString('Second notice.', $html);
    }
}
