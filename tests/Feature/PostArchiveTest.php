<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
    }

    public function test_unarchive_restores_the_post_in_the_url(): void
    {
        $first = Post::factory()->create(['client_id' => $this->client->id, 'archived_at' => now()]);
        $second = Post::factory()->create(['client_id' => $this->client->id, 'archived_at' => now()]);

        $this->actingAs($this->user)
            ->delete("/posts/{$second->id}/archive")
            ->assertRedirect("posts/{$second->id}");

        $this->assertNull($second->fresh()->archived_at);
        $this->assertNotNull($first->fresh()->archived_at);
    }

    public function test_unarchive_returns_404_for_a_post_of_another_client(): void
    {
        $post = Post::factory()->create(['client_id' => Client::factory()->create()->id, 'archived_at' => now()]);

        $this->actingAs($this->user)
            ->delete("/posts/{$post->id}/archive")
            ->assertNotFound();

        $this->assertNotNull($post->fresh()->archived_at);
    }
}
