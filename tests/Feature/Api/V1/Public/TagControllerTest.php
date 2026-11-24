<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);
    }

    protected function authenticate(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/public/v1/tags');

        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_tags(): void
    {
        $this->authenticate();

        Tag::factory()->count(5)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'client_id',
                        'posts_count',
                        'contacts_count',
                    ],
                ],
            ]);
    }

    public function test_index_filters_by_search(): void
    {
        $this->authenticate();

        Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Feature',
        ]);

        Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Bug',
        ]);

        $response = $this->getJson('/api/public/v1/tags?search=Fea');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Feature', $data[0]['title']);
    }

    public function test_index_sorts_by_allowed_fields(): void
    {
        $this->authenticate();

        Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Alpha',
        ]);

        Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Zulu',
        ]);

        $response = $this->getJson('/api/public/v1/tags?sort_by=title&sort_direction=desc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Zulu', $data[0]['title']);
        $this->assertEquals('Alpha', $data[1]['title']);
    }

    public function test_index_is_client_isolated(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        Tag::factory()->count(2)->create([
            'client_id' => $otherClient->id,
        ]);

        Tag::factory()->count(3)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/tags');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);

        foreach ($data as $tag) {
            $this->assertEquals($this->client->id, $tag['client_id']);
        }
    }

    public function test_store_creates_new_tag(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/tags', [
            'title' => 'New Tag',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'title' => 'New Tag',
                'client_id' => $this->client->id,
            ]);

        $this->assertDatabaseHas('tags', [
            'title' => 'New Tag',
            'client_id' => $this->client->id,
        ]);
    }

    public function test_store_validates_unique_title_per_client(): void
    {
        $this->authenticate();

        Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Duplicate',
        ]);

        $response = $this->postJson('/api/public/v1/tags', [
            'title' => 'Duplicate',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_modifies_tag(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Old Title',
        ]);

        $response = $this->putJson("/api/public/v1/tags/{$tag->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'title' => 'Updated Title',
            ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_update_validates_unique_title_per_client(): void
    {
        $this->authenticate();

        Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Existing',
        ]);

        $tag = Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Original',
        ]);

        $response = $this->putJson("/api/public/v1/tags/{$tag->id}", [
            'title' => 'Existing',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_destroy_deletes_tag_and_detaches_relationships(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $tag->posts()->attach($post->id);
        $tag->contacts()->attach($contact->id);

        $response = $this->deleteJson("/api/public/v1/tags/{$tag->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('taggables', [
            'tag_id' => $tag->id,
            'taggable_id' => $post->id,
            'taggable_type' => Post::class,
        ]);
    }

    public function test_destroy_returns_404_for_tag_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $tag = Tag::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->deleteJson("/api/public/v1/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_store_validates_title_is_required(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_returns_404_for_nonexistent_tag(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/public/v1/tags/999', [
            'title' => 'Renamed',
        ]);

        $response->assertStatus(404);
    }

    public function test_show_route_is_not_registered(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);

        $response = $this->getJson("/api/public/v1/tags/{$tag->id}");

        $response->assertStatus(405); // tags resource has no show endpoint
    }

    public function test_index_includes_usage_counts(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);

        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $post->tags()->attach($tag);

        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $contact->tags()->attach($tag);

        $response = $this->getJson('/api/public/v1/tags');

        $response->assertStatus(200);
        $data = collect($response->json('data'))->firstWhere('id', $tag->id);
        $this->assertSame(1, $data['posts_count']);
        $this->assertSame(1, $data['contacts_count']);
    }

    public function test_destroy_detaches_relationships_before_deleting(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $post->tags()->attach($tag);
        $contact->tags()->attach($tag);

        $response = $this->deleteJson("/api/public/v1/tags/{$tag->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertSame(0, $post->tags()->count());
        $this->assertSame(0, $contact->tags()->count());
    }

    public function test_destroy_detaches_the_tag_from_release_notes(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
        $releaseNote->tags()->attach($tag);

        $this->deleteJson("/api/public/v1/tags/{$tag->id}")->assertNoContent();

        $this->assertDatabaseMissing('taggables', ['tag_id' => $tag->id]);
    }
}
