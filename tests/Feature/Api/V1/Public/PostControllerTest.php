<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Board;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
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
        $response = $this->getJson('/api/public/v1/posts');

        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_posts(): void
    {
        $this->authenticate();

        Post::factory()->count(5)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/posts');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);

        if (! empty($responseData['data'])) {
            $response->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'content',
                        'status',
                        'client_id',
                        'contact',
                        'tags',
                        'votes',
                    ],
                ],
            ]);
        }
    }

    public function test_index_filters_by_search(): void
    {
        $this->authenticate();

        Post::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Feature Request A',
            'content' => 'Description A',
        ]);

        Post::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Bug Report B',
            'content' => 'Description B',
        ]);

        $response = $this->getJson('/api/public/v1/posts?search=Feature');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Feature Request A', $data[0]['title']);
    }

    public function test_index_filters_by_status(): void
    {
        $this->authenticate();

        Post::factory()->create([
            'client_id' => $this->client->id,
            'status' => Post::STATUS_PENDING,
        ]);

        Post::factory()->create([
            'client_id' => $this->client->id,
            'status' => Post::STATUS_IN_PROGRESS,
        ]);

        $response = $this->getJson('/api/public/v1/posts?status=pending');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(Post::STATUS_PENDING, $data[0]['status']);
    }

    public function test_index_filters_by_tag(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'feature',
        ]);

        $post1 = Post::factory()->create([
            'client_id' => $this->client->id,
        ]);
        $post1->tags()->attach($tag);

        $post2 = Post::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/posts?tag=feature');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($post1->id, $data[0]['id']);
    }

    public function test_index_filters_by_published_status(): void
    {
        $this->authenticate();

        $publishedPost = Post::factory()->create([
            'client_id' => $this->client->id,
            'published_at' => now(),
        ]);

        $unpublishedPost = Post::factory()->create([
            'client_id' => $this->client->id,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/public/v1/posts?published=true');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($publishedPost->id, $data[0]['id']);

        $response = $this->getJson('/api/public/v1/posts?published=false');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($unpublishedPost->id, $data[0]['id']);
    }

    public function test_index_sorts_by_allowed_fields(): void
    {
        $this->authenticate();

        Post::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'A Post',
            'created_at' => now()->subDays(2),
        ]);

        Post::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Z Post',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/api/public/v1/posts?sort_by=title&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('A Post', $data[0]['title']);
        $this->assertEquals('Z Post', $data[1]['title']);
    }

    public function test_store_creates_new_post(): void
    {
        $this->authenticate();

        $postData = [
            'title' => 'New Feature Request',
            'content' => 'Please add this feature',
            'status' => Post::STATUS_PENDING,
        ];

        $response = $this->postJson('/api/public/v1/posts', $postData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'status',
                'client_id',
                'tags',
                'contact',
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'New Feature Request',
            'content' => 'Please add this feature',
            'status' => Post::STATUS_PENDING,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_store_files_post_under_supplied_board(): void
    {
        $this->authenticate();

        $board = Board::factory()->create(['client_id' => $this->client->id]);

        $response = $this->postJson('/api/public/v1/posts', [
            'title' => 'Boarded',
            'content' => 'x',
            'board_id' => $board->id,
        ]);

        $response->assertStatus(201);
        $this->assertSame($board->id, Post::where('title', 'Boarded')->value('board_id'));
    }

    public function test_store_rejects_board_belonging_to_another_client(): void
    {
        $this->authenticate();

        $foreignBoard = Board::factory()->create(['client_id' => Client::factory()->create()->id]);

        $response = $this->postJson('/api/public/v1/posts', [
            'title' => 'Nope',
            'content' => 'x',
            'board_id' => $foreignBoard->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_index_filters_by_board(): void
    {
        $this->authenticate();

        $boardA = Board::factory()->create(['client_id' => $this->client->id]);
        $boardB = Board::factory()->create(['client_id' => $this->client->id]);
        $keep = Post::factory()->create(['client_id' => $this->client->id, 'board_id' => $boardA->id]);
        Post::factory()->create(['client_id' => $this->client->id, 'board_id' => $boardB->id]);

        $response = $this->getJson("/api/public/v1/posts?board_id={$boardA->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($keep->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_with_tags(): void
    {
        $this->authenticate();

        $tag1 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag2 = Tag::factory()->create(['client_id' => $this->client->id]);

        $postData = [
            'title' => 'Feature with Tags',
            'content' => 'A feature request with tags',
            'tags' => [$tag1->id, $tag2->id],
        ];

        $response = $this->postJson('/api/public/v1/posts', $postData);

        $response->assertStatus(201);

        $post = Post::where('title', 'Feature with Tags')->first();
        $this->assertCount(2, $post->tags);
        $this->assertTrue($post->tags->contains($tag1));
        $this->assertTrue($post->tags->contains($tag2));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/posts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_store_validates_status_enum(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/posts', [
            'title' => 'Test Post',
            'content' => 'Test description',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_defaults_status_to_pending(): void
    {
        $this->authenticate();

        $postData = [
            'title' => 'Test Post',
            'content' => 'Test description',
        ];

        $response = $this->postJson('/api/public/v1/posts', $postData);

        $response->assertStatus(201);

        $post = Post::where('title', 'Test Post')->first();
        $this->assertEquals(Post::STATUS_PENDING, $post->status);
    }

    public function test_show_returns_post_with_relationships(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $contact->id,
        ]);

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $post->tags()->attach($tag);

        $response = $this->getJson("/api/public/v1/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'status',
                'client_id',
                'contact' => [
                    'id',
                    'name',
                    'email',
                ],
                'tags' => [
                    '*' => ['id', 'title'],
                ],
                'votes',
                'comments',
            ]);
    }

    public function test_show_returns_404_for_nonexistent_post(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/public/v1/posts/999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_post_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $post = Post::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->getJson("/api/public/v1/posts/{$post->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_post(): void
    {
        $this->authenticate();

        $post = Post::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Original Title',
            'content' => 'Original description',
            'status' => Post::STATUS_PENDING,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated description',
            'status' => Post::STATUS_IN_PROGRESS,
        ];

        $response = $this->putJson("/api/public/v1/posts/{$post->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'status',
                'client_id',
                'tags',
                'contact',
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'content' => 'Updated description',
            'status' => Post::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_update_with_tags(): void
    {
        $this->authenticate();

        $post = Post::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $tag1 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag2 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag3 = Tag::factory()->create(['client_id' => $this->client->id]);

        $post->tags()->attach([$tag1->id, $tag2->id]);

        $updateData = [
            'title' => 'Updated Title',
            'tags' => [$tag2->id, $tag3->id],
        ];

        $response = $this->putJson("/api/public/v1/posts/{$post->id}", $updateData);

        $response->assertStatus(200);

        $post->refresh();
        $this->assertCount(2, $post->tags);
        $this->assertFalse($post->tags->contains($tag1));
        $this->assertTrue($post->tags->contains($tag2));
        $this->assertTrue($post->tags->contains($tag3));
    }

    public function test_update_validates_status_enum(): void
    {
        $this->authenticate();

        $post = Post::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->putJson("/api/public/v1/posts/{$post->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_returns_404_for_nonexistent_post(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/public/v1/posts/999', [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_post_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $post = Post::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->putJson("/api/public/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_pagination_parameters(): void
    {
        $this->authenticate();

        Post::factory()->count(25)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/posts?per_page=10');

        $response->assertStatus(200);
        $data = $response->json('data');

        if (! empty($data)) {
            $this->assertCount(10, $data);
            $meta = $response->json('meta');
            if ($meta) {
                $this->assertEquals(25, $meta['total']);
                $this->assertEquals(10, $meta['per_page']);
            }
        }
    }

    public function test_all_operations_are_client_isolated(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        Post::factory()->count(3)->create([
            'client_id' => $otherClient->id,
        ]);

        Post::factory()->count(2)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/posts');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $post) {
            $this->assertEquals($this->client->id, $post['client_id']);
        }
    }

    public function test_delete_is_not_allowed(): void
    {
        $this->authenticate();

        $post = Post::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson("/api/public/v1/posts/{$post->id}");

        $response->assertStatus(405); // Method Not Allowed
    }

    public function test_store_rejects_tags_belonging_to_another_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $foreignTag = Tag::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->postJson('/api/public/v1/posts', [
            'title' => 'Tagged post',
            'content' => 'Body',
            'tags' => [$foreignTag->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }

    public function test_update_rejects_tags_belonging_to_another_client(): void
    {
        $this->authenticate();

        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $otherClient = Client::factory()->create();
        $foreignTag = Tag::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->putJson("/api/public/v1/posts/{$post->id}", [
            'tags' => [$foreignTag->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }

    public function test_store_ignores_client_id_supplied_in_payload(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();

        $response = $this->postJson('/api/public/v1/posts', [
            'title' => 'Scoped post',
            'content' => 'Body',
            'client_id' => $otherClient->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->client->id, $response->json('client_id'));
        $this->assertDatabaseHas('posts', [
            'title' => 'Scoped post',
            'client_id' => $this->client->id,
        ]);
    }

    public function test_token_without_client_id_returns_401(): void
    {
        $token = $this->user->createToken('no-client-token', ['*']);

        $this->withToken($token->plainTextToken);

        $response = $this->getJson('/api/public/v1/posts');

        $response->assertStatus(401);
    }

    public function test_client_id_query_parameter_cannot_override_token_scope(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        Post::factory()->count(3)->create(['client_id' => $otherClient->id]);
        Post::factory()->create(['client_id' => $this->client->id]);

        $response = $this->getJson('/api/public/v1/posts?client_id='.$otherClient->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->client->id, $data[0]['client_id']);
    }
}
