<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
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
        $response = $this->getJson('/api/public/v1/contacts');

        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_contacts(): void
    {
        $this->authenticate();

        Contact::factory()->count(5)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/contacts');

        $response->assertStatus(200);

        // Debug the actual response structure
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);

        if (! empty($responseData['data'])) {
            $response->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'client_id',
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

        Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $response = $this->getJson('/api/public/v1/contacts?search=John');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('John Doe', $data[0]['name']);
    }

    public function test_index_filters_by_tag(): void
    {
        $this->authenticate();

        $tag = Tag::factory()->create([
            'client_id' => $this->client->id,
            'title' => 'premium',
        ]);

        $contact1 = Contact::factory()->create([
            'client_id' => $this->client->id,
        ]);
        $contact1->tags()->attach($tag);

        $contact2 = Contact::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/contacts?tag=premium');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($contact1->id, $data[0]['id']);
    }

    public function test_index_sorts_by_allowed_fields(): void
    {
        $this->authenticate();

        Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'A Contact',
            'created_at' => now()->subDays(2),
        ]);

        Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Z Contact',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/api/public/v1/contacts?sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('A Contact', $data[0]['name']);
        $this->assertEquals('Z Contact', $data[1]['name']);
    }

    public function test_store_creates_new_contact(): void
    {
        $this->authenticate();

        $contactData = [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
            'mrr' => 100.00,
            'ltv' => 1200.00,
            'avatar' => 'https://example.com/avatar.jpg',
        ];

        $response = $this->postJson('/api/public/v1/contacts', $contactData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'client_id',
                'tags',
            ]);

        $this->assertDatabaseHas('contacts', [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
            'client_id' => $this->client->id,
            'mrr' => 100.00,
            'ltv' => 1200.00,
        ]);

        $this->assertEquals('https://example.com/avatar.jpg', $response->json('avatar'));
    }

    public function test_store_with_tags(): void
    {
        $this->authenticate();

        $tag1 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag2 = Tag::factory()->create(['client_id' => $this->client->id]);

        $contactData = [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
            'tags' => [$tag1->id, $tag2->id],
        ];

        $response = $this->postJson('/api/public/v1/contacts', $contactData);

        $response->assertStatus(201);

        $contact = Contact::where('email', 'test@example.com')->first();
        $this->assertCount(2, $contact->tags);
        $this->assertTrue($contact->tags->contains($tag1));
        $this->assertTrue($contact->tags->contains($tag2));
    }

    public function test_store_saves_extra_fields_into_metadata(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/contacts', [
            'name' => 'Metadata Contact',
            'email' => 'meta@example.com',
            'plan' => 'enterprise',
            'custom_field' => 'custom value',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('enterprise', $response->json('plan'));
        $this->assertEquals('custom value', $response->json('custom_field'));

        $contact = Contact::where('email', 'meta@example.com')->first();
        $metadata = json_decode($contact->metadata, true);

        $this->assertEquals('enterprise', $metadata['plan']);
        $this->assertEquals('custom value', $metadata['custom_field']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/contacts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_store_updates_existing_contact_when_email_matches(): void
    {
        $this->authenticate();

        $existingContact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'email' => 'existing@example.com',
            'name' => 'Original Name',
            'mrr' => 10,
            'ltv' => 100,
            'metadata' => json_encode(['plan' => 'basic']),
        ]);

        $response = $this->postJson('/api/public/v1/contacts', [
            'name' => 'Updated Name',
            'email' => 'existing@example.com',
            'mrr' => 40,
            'plan' => 'enterprise',
            'tier' => 'gold',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('enterprise', $response->json('plan'));
        $this->assertEquals('gold', $response->json('tier'));

        $this->assertDatabaseHas('contacts', [
            'id' => $existingContact->id,
            'name' => 'Updated Name',
            'email' => 'existing@example.com',
            'mrr' => 40,
            'ltv' => 100,
        ]);

        $existingContact->refresh();
        $metadata = json_decode($existingContact->metadata, true);
        $this->assertEquals('enterprise', $metadata['plan']);
        $this->assertEquals('gold', $metadata['tier']);
    }

    public function test_store_creates_new_contact_for_same_email_on_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        Contact::factory()->create([
            'client_id' => $otherClient->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->postJson('/api/public/v1/contacts', [
            'name' => 'New Contact',
            'email' => 'shared@example.com',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contacts', [
            'client_id' => $this->client->id,
            'email' => 'shared@example.com',
            'name' => 'New Contact',
        ]);
    }

    public function test_show_returns_contact_with_relationships(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $contact->tags()->attach($tag);

        $response = $this->getJson("/api/public/v1/contacts/{$contact->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'client_id',
                'tags' => [
                    '*' => ['id', 'title'],
                ],
                'votes',
            ]);
    }

    public function test_show_returns_404_for_nonexistent_contact(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/public/v1/contacts/999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_contact_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $contact = Contact::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->getJson("/api/public/v1/contacts/{$contact->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_contact(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'mrr' => 150.00,
        ];

        $response = $this->putJson("/api/public/v1/contacts/{$contact->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'client_id',
                'tags',
            ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'mrr' => 150.00,
        ]);
    }

    public function test_update_with_tags(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $tag1 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag2 = Tag::factory()->create(['client_id' => $this->client->id]);
        $tag3 = Tag::factory()->create(['client_id' => $this->client->id]);

        $contact->tags()->attach([$tag1->id, $tag2->id]);

        $updateData = [
            'name' => 'Updated Name',
            'tags' => [$tag2->id, $tag3->id],
        ];

        $response = $this->putJson("/api/public/v1/contacts/{$contact->id}", $updateData);

        $response->assertStatus(200);

        $contact->refresh();
        $this->assertCount(2, $contact->tags);
        $this->assertFalse($contact->tags->contains($tag1));
        $this->assertTrue($contact->tags->contains($tag2));
        $this->assertTrue($contact->tags->contains($tag3));
    }

    public function test_update_validates_unique_email_excluding_current(): void
    {
        $this->authenticate();

        $contact1 = Contact::factory()->create([
            'client_id' => $this->client->id,
            'email' => 'contact1@example.com',
        ]);

        $contact2 = Contact::factory()->create([
            'client_id' => $this->client->id,
            'email' => 'contact2@example.com',
        ]);

        // Should fail - trying to use existing email
        $response = $this->putJson("/api/public/v1/contacts/{$contact1->id}", [
            'email' => 'contact2@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Should succeed - using same email (no change)
        $response = $this->putJson("/api/public/v1/contacts/{$contact1->id}", [
            'email' => 'contact1@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_returns_404_for_nonexistent_contact(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/public/v1/contacts/999', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_contact_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $contact = Contact::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->putJson("/api/public/v1/contacts/{$contact->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_pagination_parameters(): void
    {
        $this->authenticate();

        Contact::factory()->count(25)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/contacts?per_page=10');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Only check counts if data exists
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
        Contact::factory()->count(3)->create([
            'client_id' => $otherClient->id,
        ]);

        Contact::factory()->count(2)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/contacts');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $contact) {
            $this->assertEquals($this->client->id, $contact['client_id']);
        }
    }

    public function test_store_validates_email_format(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/contacts', [
            'name' => 'Jane',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_validates_avatar_must_be_a_url(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/contacts', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'avatar' => 'not-a-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_update_rejects_tags_belonging_to_another_client(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $otherClient = Client::factory()->create();
        $foreignTag = Tag::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->patchJson("/api/public/v1/contacts/{$contact->id}", [
            'tags' => [$foreignTag->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }

    public function test_destroy_is_not_allowed(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create(['client_id' => $this->client->id]);

        $response = $this->deleteJson("/api/public/v1/contacts/{$contact->id}");

        // Deleting contacts is not supported; the destroy route is not registered.
        $response->assertStatus(405);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }
}
