<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultBoardTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Board $publicBoard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->client->users()->attach(User::factory()->create());
        $this->publicBoard = $this->client->boards()->sole();
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    private function privateBoardFirst(): Board
    {
        return Board::factory()->private()->create([
            'client_id' => $this->client->id,
            'position' => $this->publicBoard->position - 1,
        ]);
    }

    private function signInContact(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $this->app['auth']->guard('contact')->setUser($contact);
    }

    private function postAsContact(): Post
    {
        $this->signInContact();

        $this->post($this->siteUrl('/posts'), ['title' => 'Dark mode', 'content' => 'Add a dark theme.'])
            ->assertRedirect();

        // The contact routes make `contact` the default guard. Reset the guards so the next request is a guest request.
        $this->app['auth']->forgetGuards();

        return Post::query()->where('title', 'Dark mode')->sole();
    }

    public function test_a_new_post_skips_a_private_board_that_comes_first(): void
    {
        $this->privateBoardFirst();

        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $this->assertSame($this->publicBoard->id, $post->board_id);
    }

    public function test_a_contact_post_from_the_public_site_skips_a_private_board_that_comes_first(): void
    {
        $this->privateBoardFirst();

        $post = $this->postAsContact();

        $this->assertSame($this->publicBoard->id, $post->board_id);
        $this->get($this->siteUrl('/posts/'.$post->id))->assertOk()->assertSee('Dark mode');
    }

    public function test_a_named_private_board_is_kept(): void
    {
        $private = $this->privateBoardFirst();

        $post = Post::factory()->create(['client_id' => $this->client->id, 'board_id' => $private->id]);

        $this->assertSame($private->id, $post->board_id);
    }

    public function test_a_new_post_goes_to_the_first_board_when_every_board_is_private(): void
    {
        $this->publicBoard->update(['is_public' => false]);
        $first = $this->privateBoardFirst();

        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $this->assertSame($first->id, $post->board_id);
    }

    public function test_a_contact_post_is_kept_for_the_team_when_every_board_is_private(): void
    {
        $this->publicBoard->update(['is_public' => false]);
        $first = $this->privateBoardFirst();

        $post = $this->postAsContact();

        $this->assertSame($first->id, $post->board_id);
        $this->get($this->siteUrl('/posts/'.$post->id))->assertNotFound();
    }

    public function test_the_contact_goes_to_the_post_list_when_the_new_post_is_on_a_private_board(): void
    {
        $this->publicBoard->update(['is_public' => false]);
        $this->signInContact();

        $this->post($this->siteUrl('/posts'), ['title' => 'Dark mode', 'content' => 'Add a dark theme.'])
            ->assertRedirect('/posts')
            ->assertSessionHas('success');
    }
}
