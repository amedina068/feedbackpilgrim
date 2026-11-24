<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGuestActionsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create(['name' => 'Acme']);
        $author = Contact::factory()->create(['client_id' => $this->client->id]);
        $this->post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $author->id,
            'published_at' => now(),
        ]);
    }

    public function test_a_guest_sees_a_read_only_vote_count_with_a_sign_in_hint(): void
    {
        $this->get($this->siteUrl('/posts/'.$this->post->id))
            ->assertOk()
            ->assertDontSee('action="/votes"', false)
            ->assertSee('To vote, open this board from your Acme account.');
    }

    public function test_a_guest_sees_how_to_comment(): void
    {
        $this->get($this->siteUrl('/posts/'.$this->post->id))
            ->assertOk()
            ->assertSee('To comment, open this board from your Acme account.');
    }

    public function test_a_contact_sees_the_vote_button_and_the_comment_form(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);

        $this->app['auth']->guard('contact')->setUser($contact);

        $this->get($this->siteUrl('/posts/'.$this->post->id))
            ->assertOk()
            ->assertSee('action="/votes"', false)
            ->assertSee('action="/comments"', false)
            ->assertDontSee('To comment, open this board');
    }

    public function test_a_guest_action_stays_on_the_public_site(): void
    {
        $this->post($this->siteUrl('/votes'), [
            'model_type' => Post::class,
            'model_id' => $this->post->id,
        ])
            ->assertRedirect('/posts')
            ->assertSessionHas('info', 'To vote, comment or post, open this board from your account in the product.');

        $this->get($this->siteUrl('/posts/create'))
            ->assertRedirect('/posts');
    }

    public function test_a_guest_json_request_gets_401(): void
    {
        $this->postJson($this->siteUrl('/votes'), [])
            ->assertUnauthorized();
    }

    public function test_an_expired_sign_in_link_explains_what_happened(): void
    {
        $this->get($this->siteUrl('/authenticate/some-uuid'))
            ->assertRedirect('/posts')
            ->assertSessionHas('info', 'This sign-in link is not valid or has expired. Open the board again from your Acme account.');
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }
}
