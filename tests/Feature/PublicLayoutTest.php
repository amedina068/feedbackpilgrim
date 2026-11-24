<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create(['name' => 'Acme']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'release notes' => ['/release-notes', 'Release Notes'],
            'posts' => ['/posts', 'Posts'],
            'roadmap' => ['/roadmap', 'Roadmap'],
            'testimonials' => ['/testimonials', 'Testimonials'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_marks_its_own_link_as_the_current_page(string $path, string $title): void
    {
        $html = $this->get($this->siteUrl($path))
            ->assertOk()
            ->assertSee('<nav aria-label="Main"', false)
            ->assertSee($title)
            ->getContent();

        $this->assertSame(2, substr_count($html, 'aria-current="page"'), 'The sidebar and the menu each mark one link.');
        $this->assertMatchesRegularExpression('#href="'.preg_quote($path, '#').'"\s+aria-current="page"#', $html);
    }

    public function test_the_home_page_marks_no_link_as_the_current_page(): void
    {
        $this->get($this->siteUrl('/'))
            ->assertOk()
            ->assertSee('<nav aria-label="Main"', false)
            ->assertDontSee('aria-current="page"', false);
    }

    public function test_the_mobile_menu_is_a_dialog_that_the_menu_button_opens(): void
    {
        $this->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertSee('<dialog', false)
            ->assertSee('id="public-menu"', false)
            ->assertSee('aria-label="Menu"', false)
            ->assertSee("document.getElementById('public-menu').showModal()", false)
            ->assertSee('Close menu');
    }

    public function test_the_layout_links_back_to_the_website_of_the_project(): void
    {
        $this->client->settings()->create(['name' => ClientSetting::NAME_DOMAIN, 'value' => 'acme.example.com']);

        $html = $this->get($this->siteUrl('/posts'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'href="http://acme.example.com"'), 'The sidebar and the menu each have the link.');
        $this->assertStringContainsString('Back to Acme', $html);
    }

    public function test_the_layout_has_no_back_link_without_a_website(): void
    {
        $this->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertDontSee('Back to Acme');
    }

    public function test_the_layout_shows_the_logo_of_the_project(): void
    {
        $this->client->update(['logo' => 'https://cdn.example.com/acme.png']);

        $this->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertSee('src="https://cdn.example.com/acme.png"', false);
    }

    public function test_the_post_page_shows_the_breadcrumb_and_the_comments(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Ada Lovelace']);
        $post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $contact->id,
            'title' => 'Dark mode',
            'status' => Post::STATUS_IN_PROGRESS,
            'published_at' => now(),
        ]);
        $post->comments()->create(['contact_id' => $contact->id, 'content' => 'Yes, **please**']);

        $this->get($this->siteUrl('/posts/'.$post->id))
            ->assertOk()
            ->assertSeeInOrder(['<nav aria-label="Breadcrumb"', 'href="/"', 'Acme', 'href="/posts"', 'Posts', 'Dark mode', '</nav>'], false)
            ->assertSeeInOrder(['<article', 'Ada Lovelace', '<time', '<strong>please</strong>', '</article>'], false);
    }

    public function test_the_post_list_shows_the_commenters_and_the_comment_count(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Ada Lovelace']);
        $post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $contact->id,
            'title' => 'Dark mode',
            'status' => Post::STATUS_IN_PROGRESS,
            'published_at' => now(),
        ]);
        $post->comments()->create(['contact_id' => $contact->id, 'content' => 'First']);
        $post->comments()->create(['contact_id' => $contact->id, 'content' => 'Second']);

        $this->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertSeeInOrder(['<h1', 'Posts', '</h1>', 'Dark mode', 'aria-label="Commenters"', 'alt="Ada Lovelace"', 'Comments:</span>', '2'], false);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }
}
