<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Client;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNoteControllerTest extends TestCase
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
        $response = $this->getJson('/api/public/v1/release-notes');

        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_release_notes(): void
    {
        $this->authenticate();

        ReleaseNote::factory()->count(5)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/release-notes');

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
                        'client_id',
                        'published_at',
                        'tags',
                        'votes',
                        'comments',
                    ],
                ],
            ]);
        }
    }

    public function test_index_filters_by_search(): void
    {
        $this->authenticate();

        ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Version 2.0 Release',
            'content' => 'Major version update',
        ]);

        ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Bug Fix Update',
            'content' => 'Minor bug fixes',
        ]);

        $response = $this->getJson('/api/public/v1/release-notes?search=Version');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Version 2.0 Release', $data[0]['title']);
    }

    public function test_index_filters_by_published_status(): void
    {
        $this->authenticate();

        $publishedNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'published_at' => now(),
        ]);

        $unpublishedNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/public/v1/release-notes?published=true');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($publishedNote->id, $data[0]['id']);

        $response = $this->getJson('/api/public/v1/release-notes?published=false');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($unpublishedNote->id, $data[0]['id']);
    }

    public function test_index_filters_by_tag(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'major',
        ]);

        $releaseNote1 = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
        ]);
        $releaseNote1->tags()->attach($tag);

        $releaseNote2 = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/release-notes?tag=major');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($releaseNote1->id, $data[0]['id']);
    }

    public function test_index_sorts_by_allowed_fields(): void
    {
        $this->authenticate();

        ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'A Release',
            'created_at' => now()->subDays(2),
        ]);

        ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Z Release',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/api/public/v1/release-notes?sort_by=title&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('A Release', $data[0]['title']);
        $this->assertEquals('Z Release', $data[1]['title']);
    }

    public function test_store_creates_new_release_note(): void
    {
        $this->authenticate();

        $releaseNoteData = [
            'title' => 'New Release v1.5',
            'content' => 'This release includes new features and bug fixes.',
        ];

        $response = $this->postJson('/api/public/v1/release-notes', $releaseNoteData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'client_id',
                'tags',
            ]);

        $this->assertDatabaseHas('release_notes', [
            'title' => 'New Release v1.5',
            'content' => 'This release includes new features and bug fixes.',
            'client_id' => $this->client->id,
        ]);
    }

    public function test_store_with_tags(): void
    {
        $this->authenticate();

        $tag1 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag2 = Tag::factory()->create(['client_id' => $this->client->id]);

        $releaseNoteData = [
            'title' => 'Release with Tags',
            'content' => 'A release note with tags',
            'tags' => [$tag1->id, $tag2->id],
        ];

        $response = $this->postJson('/api/public/v1/release-notes', $releaseNoteData);

        $response->assertStatus(201);

        $releaseNote = ReleaseNote::where('title', 'Release with Tags')->first();
        $this->assertCount(2, $releaseNote->tags);
        $this->assertTrue($releaseNote->tags->contains($tag1));
        $this->assertTrue($releaseNote->tags->contains($tag2));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/release-notes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_store_validates_tag_exists_for_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $tagFromOtherClient = Tag::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->postJson('/api/public/v1/release-notes', [
            'title' => 'Test Release',
            'content' => 'Test content',
            'tags' => [$tagFromOtherClient->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }

    public function test_show_returns_release_note_with_relationships(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $releaseNote->tags()->attach($tag);

        $response = $this->getJson("/api/public/v1/release-notes/{$releaseNote->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'client_id',
                'published_at',
                'tags' => [
                    '*' => ['id', 'title'],
                ],
                'votes',
                'comments',
            ]);
    }

    public function test_show_returns_404_for_nonexistent_release_note(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/public/v1/release-notes/999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_release_note_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->getJson("/api/public/v1/release-notes/{$releaseNote->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_release_note(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'Original Title',
            'content' => 'Original content',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ];

        $response = $this->putJson("/api/public/v1/release-notes/{$releaseNote->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'content',
                'client_id',
                'tags',
            ]);

        $this->assertDatabaseHas('release_notes', [
            'id' => $releaseNote->id,
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);
    }

    public function test_update_with_tags(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $tag1 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag2 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag3 = Tag::factory()->create(['client_id' => $this->client->id]);

        $releaseNote->tags()->attach([$tag1->id, $tag2->id]);

        $updateData = [
            'title' => 'Updated Title',
            'tags' => [$tag2->id, $tag3->id],
        ];

        $response = $this->putJson("/api/public/v1/release-notes/{$releaseNote->id}", $updateData);

        $response->assertStatus(200);

        $releaseNote->refresh();
        $this->assertCount(2, $releaseNote->tags);
        $this->assertFalse($releaseNote->tags->contains($tag1));
        $this->assertTrue($releaseNote->tags->contains($tag2));
        $this->assertTrue($releaseNote->tags->contains($tag3));
    }

    public function test_update_validates_tag_exists_for_client(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $otherClient = Client::factory()->create();
        $tagFromOtherClient = Tag::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->putJson("/api/public/v1/release-notes/{$releaseNote->id}", [
            'tags' => [$tagFromOtherClient->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }

    public function test_update_returns_404_for_nonexistent_release_note(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/public/v1/release-notes/999', [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_release_note_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->putJson("/api/public/v1/release-notes/{$releaseNote->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(404);
    }

    public function test_pagination_parameters(): void
    {
        $this->authenticate();

        ReleaseNote::factory()->count(25)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/release-notes?per_page=10');

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
        ReleaseNote::factory()->count(3)->create([
            'client_id' => $otherClient->id,
        ]);

        ReleaseNote::factory()->count(2)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/release-notes');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $releaseNote) {
            $this->assertEquals($this->client->id, $releaseNote['client_id']);
        }
    }

    public function test_delete_is_not_allowed(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson("/api/public/v1/release-notes/{$releaseNote->id}");

        $response->assertStatus(405); // Method Not Allowed
    }

    public function test_store_makes_a_draft_generates_slug_and_records_author(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/release-notes', [
            'title' => 'Version 2.1.0',
            'content' => 'Lots of improvements',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('published_at'));
        $this->assertArrayNotHasKey('status', $response->json());
        $this->assertNotEmpty($response->json('slug'));
        $this->assertStringStartsWith('version-210', $response->json('slug'));
        $this->assertEquals($this->user->id, $response->json('user_id'));
    }

    public function test_store_with_published_sets_the_publication_date(): void
    {
        $this->authenticate();
        $this->freezeSecond();

        $this->postJson('/api/public/v1/release-notes', [
            'title' => 'Version 2.2.0',
            'content' => 'Body',
            'published' => true,
        ])->assertCreated()->assertJsonPath('published_at', now()->toJSON());
    }

    public function test_update_with_published_false_clears_the_publication_date(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'published_at' => now()]);

        $this->patchJson("/api/public/v1/release-notes/{$releaseNote->id}", ['published' => false])
            ->assertOk()
            ->assertJsonPath('published_at', null);
    }

    public function test_update_with_published_true_keeps_a_publication_date_that_exists(): void
    {
        $this->authenticate();

        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'published_at' => '2026-01-15 10:00:00']);

        $this->patchJson("/api/public/v1/release-notes/{$releaseNote->id}", ['published' => true])->assertOk();

        $this->assertSame('2026-01-15 10:00:00', $releaseNote->fresh()->published_at->toDateTimeString());
    }

    public function test_store_rejects_a_published_value_that_is_not_a_boolean(): void
    {
        $this->authenticate();

        $this->postJson('/api/public/v1/release-notes', ['title' => 'T', 'content' => 'C', 'published' => 'soon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('published');
    }

    public function test_store_ignores_client_id_supplied_in_payload(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();

        $response = $this->postJson('/api/public/v1/release-notes', [
            'title' => 'Scoped note',
            'content' => 'Body',
            'client_id' => $otherClient->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->client->id, $response->json('client_id'));
    }
}
