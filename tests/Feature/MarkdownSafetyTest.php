<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contacts write post content in Markdown. The rendered HTML must not keep
 * script links or raw HTML from the contact.
 */
class MarkdownSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Client, 1: User, 2: Post}
     */
    private function postWithContent(string $content): array
    {
        $client = Client::factory()->create();
        $admin = User::factory()->create();
        $client->users()->attach($admin);
        $admin->adminContactFor($client);
        $author = Contact::factory()->create(['client_id' => $client->id, 'name' => 'Eve']);
        $post = Post::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $author->id,
            'title' => 'Bug',
            'content' => $content,
            'published_at' => now(),
        ]);

        return [$client, $admin, $post];
    }

    private function publicPostUrl(Client $client, Post $post): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').'/posts/'.$post->id;
    }

    public function test_javascript_links_are_not_rendered_on_the_public_and_admin_pages(): void
    {
        [$client, $admin, $post] = $this->postWithContent('[See screenshot](javascript:alert(document.domain))');

        $this->get($this->publicPostUrl($client, $post))
            ->assertOk()
            ->assertSee('See screenshot')
            ->assertDontSee('href="javascript:', false);

        $this->actingAs($admin)->withSession(['fp_client_id' => $client->id])
            ->get('/posts/'.$post->id)
            ->assertOk()
            ->assertDontSee('href="javascript:', false);
    }

    public function test_raw_html_in_markdown_is_escaped(): void
    {
        [$client, , $post] = $this->postWithContent('Hello <img src=x onerror=alert(1)>');

        $this->get($this->publicPostUrl($client, $post))
            ->assertOk()
            ->assertDontSee('<img src=x onerror', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_normal_links_still_render(): void
    {
        [$client, , $post] = $this->postWithContent('[Docs](https://example.com/docs)');

        $this->get($this->publicPostUrl($client, $post))
            ->assertOk()
            ->assertSee('href="https://example.com/docs"', false);
    }
}
