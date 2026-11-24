<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TagDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);

        $this->tag = Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'billing']);
    }

    public function test_the_tag_page_has_the_rename_and_delete_forms(): void
    {
        $this->actingAs($this->user)
            ->get("/tags/{$this->tag->id}")
            ->assertOk()
            ->assertSee('Rename')
            ->assertSee('Delete tag');
    }

    public function test_an_admin_renames_a_tag(): void
    {
        $this->actingAs($this->user)
            ->patch("/tags/{$this->tag->id}", ['title' => 'payments'])
            ->assertRedirect("tags/{$this->tag->id}")
            ->assertSessionHas('success', 'Tag renamed');

        $this->assertSame('payments', $this->tag->fresh()->title);
    }

    public function test_a_rename_to_a_title_that_exists_is_refused(): void
    {
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'payments']);

        $this->actingAs($this->user)
            ->from("/tags/{$this->tag->id}")
            ->patch("/tags/{$this->tag->id}", ['title' => 'payments'])
            ->assertRedirect("/tags/{$this->tag->id}")
            ->assertSessionHasErrors('title');

        $this->assertSame('billing', $this->tag->fresh()->title);
    }

    public function test_an_admin_deletes_a_tag_and_its_links(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id]);
        $post->tags()->attach($this->tag);
        $releaseNote->tags()->attach($this->tag);

        $this->actingAs($this->user)
            ->delete("/tags/{$this->tag->id}")
            ->assertRedirect('tags')
            ->assertSessionHas('success', 'Tag deleted');

        $this->assertModelMissing($this->tag);
        $this->assertDatabaseMissing('taggables', ['tag_id' => $this->tag->id]);
    }

    public function test_a_tag_of_another_client_returns_404(): void
    {
        $other = Tag::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'theirs']);

        $this->actingAs($this->user)->patch("/tags/{$other->id}", ['title' => 'mine'])->assertNotFound();
        $this->actingAs($this->user)->delete("/tags/{$other->id}")->assertNotFound();

        $this->assertSame('theirs', $other->fresh()->title);
    }
}
