<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateBoardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Post $publicPost;

    private Post $privatePost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $planned = $this->client->stages()->ofType(Stage::TYPE_PLANNED)->first();
        $privateBoard = Board::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Internal',
            'is_public' => false,
        ]);
        $author = Contact::factory()->create(['client_id' => $this->client->id]);

        $this->publicPost = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $author->id,
            'stage_id' => $planned->id,
            'title' => 'Public board idea',
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $this->privatePost = Post::factory()->create([
            'client_id' => $this->client->id,
            'board_id' => $privateBoard->id,
            'contact_id' => $author->id,
            'stage_id' => $planned->id,
            'title' => 'Private board secret',
            'published_at' => now(),
            'archived_at' => null,
        ]);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    private function contact(): Contact
    {
        return Contact::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_public_post_list_hides_posts_of_private_boards(): void
    {
        $this->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertSee('Public board idea')
            ->assertDontSee('Private board secret');
    }

    public function test_public_post_page_returns_404_for_a_post_of_a_private_board(): void
    {
        $this->get($this->siteUrl('/posts/'.$this->publicPost->id))->assertOk();

        $this->get($this->siteUrl('/posts/'.$this->privatePost->id))->assertNotFound();
    }

    public function test_public_home_hides_posts_of_private_boards(): void
    {
        $this->get($this->siteUrl('/'))
            ->assertOk()
            ->assertSee('Public board idea')
            ->assertDontSee('Private board secret');
    }

    public function test_roadmap_and_iframe_roadmap_hide_posts_of_private_boards(): void
    {
        $this->get($this->siteUrl('/roadmap'))
            ->assertOk()
            ->assertSee('Public board idea')
            ->assertDontSee('Private board secret');

        $this->get($this->siteUrl('/iframe/roadmap'))
            ->assertOk()
            ->assertSee('Public board idea')
            ->assertDontSee('Private board secret');
    }

    public function test_contact_cannot_comment_on_a_post_of_a_private_board(): void
    {
        $this->actingAs($this->contact(), 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => Post::class,
                'model_id' => $this->privatePost->id,
                'content' => 'Hello',
            ])
            ->assertNotFound();

        $this->assertSame(0, $this->privatePost->comments()->count());
    }

    public function test_contact_cannot_vote_on_a_post_of_a_private_board(): void
    {
        $contact = $this->contact();

        $this->actingAs($contact, 'contact')
            ->post($this->siteUrl('/posts/'.$this->privatePost->id.'/votes'))
            ->assertNotFound();

        $this->actingAs($contact, 'contact')
            ->post($this->siteUrl('/votes'), [
                'model_type' => Post::class,
                'model_id' => $this->privatePost->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, $this->privatePost->votes()->count());
    }

    public function test_contact_cannot_vote_on_an_unpublished_post(): void
    {
        $this->privatePost->update(['board_id' => $this->publicPost->board_id, 'published_at' => null]);

        $this->actingAs($this->contact(), 'contact')
            ->post($this->siteUrl('/posts/'.$this->privatePost->id.'/votes'))
            ->assertNotFound();

        $this->assertSame(0, $this->privatePost->votes()->count());
    }

    public function test_contact_can_still_vote_and_comment_on_a_post_of_a_public_board(): void
    {
        $contact = $this->contact();

        $this->actingAs($contact, 'contact')
            ->post($this->siteUrl('/posts/'.$this->publicPost->id.'/votes'))
            ->assertRedirect();

        $this->actingAs($contact, 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => Post::class,
                'model_id' => $this->publicPost->id,
                'content' => 'Nice',
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->publicPost->votes()->count());
        $this->assertSame(1, $this->publicPost->comments()->count());
    }
}
