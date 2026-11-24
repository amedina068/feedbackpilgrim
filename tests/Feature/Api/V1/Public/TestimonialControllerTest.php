<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialControllerTest extends TestCase
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
        $response = $this->getJson('/api/public/v1/testimonials');

        $response->assertStatus(401);
    }

    public function test_index_returns_paginated_testimonials(): void
    {
        $this->authenticate();

        Testimonial::factory()->count(5)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/testimonials');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);

        if (! empty($responseData['data'])) {
            $response->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'description',
                        'rating',
                        'username',
                        'client_id',
                        'contact',
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

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'description' => 'Great product!',
            'username' => 'john_doe',
        ]);

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'description' => 'Excellent service',
            'username' => 'jane_smith',
        ]);

        $response = $this->getJson('/api/public/v1/testimonials?search=Great');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Great product!', $data[0]['description']);
    }

    public function test_index_filters_by_approved_status(): void
    {
        $this->authenticate();

        $approvedTestimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => now(),
        ]);

        $pendingTestimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => null,
        ]);

        $response = $this->getJson('/api/public/v1/testimonials?approved=true');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($approvedTestimonial->id, $data[0]['id']);

        // Default behavior is to show pending testimonials
        $response = $this->getJson('/api/public/v1/testimonials');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($pendingTestimonial->id, $data[0]['id']);
    }

    public function test_index_reads_approved_false_as_pending(): void
    {
        $this->authenticate();

        Testimonial::factory()->create(['client_id' => $this->client->id, 'approved_at' => now()]);
        $pending = Testimonial::factory()->create(['client_id' => $this->client->id, 'approved_at' => null]);

        foreach (['false', '0'] as $value) {
            $this->getJson("/api/public/v1/testimonials?approved={$value}")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $pending->id);
        }
    }

    public function test_index_filters_by_rating(): void
    {
        $this->authenticate();

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'rating' => 5,
        ]);

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'rating' => 3,
        ]);

        $response = $this->getJson('/api/public/v1/testimonials?rating=5');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(5, $data[0]['rating']);
    }

    public function test_index_sorts_by_allowed_fields(): void
    {
        $this->authenticate();

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'rating' => 3,
            'created_at' => now()->subDays(2),
        ]);

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'rating' => 5,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/api/public/v1/testimonials?sort_by=rating&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(3, $data[0]['rating']);
        $this->assertEquals(5, $data[1]['rating']);
    }

    public function test_index_without_sort_by_lists_the_newest_testimonials_first(): void
    {
        $this->authenticate();

        $oldest = Testimonial::factory()->create(['client_id' => $this->client->id, 'created_at' => now()->subDays(3)]);
        $newest = Testimonial::factory()->create(['client_id' => $this->client->id, 'created_at' => now()->subDay()]);

        $response = $this->getJson('/api/public/v1/testimonials')->assertOk();

        $this->assertSame([$newest->id, $oldest->id], array_column($response->json('data'), 'id'));
    }

    public function test_store_creates_new_testimonial(): void
    {
        $this->authenticate();

        $testimonialData = [
            'description' => 'Amazing product, highly recommend!',
            'rating' => 5,
            'username' => 'happy_customer',
        ];

        $response = $this->postJson('/api/public/v1/testimonials', $testimonialData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'description',
                'rating',
                'username',
                'client_id',
                'contact',
            ]);

        $this->assertDatabaseHas('testimonials', [
            'description' => 'Amazing product, highly recommend!',
            'rating' => 5,
            'username' => 'happy_customer',
            'client_id' => $this->client->id,
        ]);
    }

    public function test_store_with_contact(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create(['client_id' => $this->client->id]);

        $testimonialData = [
            'description' => 'Great experience!',
            'rating' => 4,
            'username' => 'test_user',
            'contact_id' => $contact->id,
        ];

        $response = $this->postJson('/api/public/v1/testimonials', $testimonialData);

        $response->assertStatus(201);

        $testimonial = Testimonial::where('description', 'Great experience!')->first();
        $this->assertEquals($contact->id, $testimonial->contact_id);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/testimonials', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_validates_rating_range(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/testimonials', [
            'description' => 'Test testimonial',
            'rating' => 6, // Invalid rating > 5
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        $response = $this->postJson('/api/public/v1/testimonials', [
            'description' => 'Test testimonial',
            'rating' => 0, // Invalid rating < 1
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_store_validates_contact_exists_for_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $contactFromOtherClient = Contact::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->postJson('/api/public/v1/testimonials', [
            'description' => 'Test testimonial',
            'contact_id' => $contactFromOtherClient->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contact_id']);
    }

    public function test_show_returns_testimonial_with_relationships(): void
    {
        $this->authenticate();

        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->getJson("/api/public/v1/testimonials/{$testimonial->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'rating',
                'username',
                'subtitle',
                'avatar_image',
                'client_id',
                'contact' => [
                    'id',
                    'name',
                    'email',
                ],
                'votes',
                'comments',
            ]);
    }

    public function test_show_returns_404_for_nonexistent_testimonial(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/public/v1/testimonials/999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_testimonial_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $testimonial = Testimonial::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->getJson("/api/public/v1/testimonials/{$testimonial->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_testimonial(): void
    {
        $this->authenticate();

        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'description' => 'Original content',
            'rating' => 3,
            'username' => 'original_user',
        ]);

        $updateData = [
            'description' => 'Updated content',
            'rating' => 5,
            'username' => 'updated_user',
        ];

        $response = $this->putJson("/api/public/v1/testimonials/{$testimonial->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'rating',
                'username',
                'subtitle',
                'avatar_image',
                'client_id',
                'contact',
            ]);

        $this->assertDatabaseHas('testimonials', [
            'id' => $testimonial->id,
            'description' => 'Updated content',
            'rating' => 5,
            'username' => 'updated_user',
        ]);
    }

    public function test_update_can_approve_testimonial(): void
    {
        $this->authenticate();

        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => null,
        ]);

        $approvalDate = now()->format('Y-m-d H:i:s');
        $updateData = [
            'approved_at' => $approvalDate,
        ];

        $response = $this->putJson("/api/public/v1/testimonials/{$testimonial->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('testimonials', [
            'id' => $testimonial->id,
            'approved_at' => $approvalDate,
        ]);
    }

    public function test_update_validates_rating_range(): void
    {
        $this->authenticate();

        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->putJson("/api/public/v1/testimonials/{$testimonial->id}", [
            'rating' => 6, // Invalid rating > 5
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_update_returns_404_for_nonexistent_testimonial(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/public/v1/testimonials/999', [
            'description' => 'Updated content',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_testimonial_from_different_client(): void
    {
        $this->authenticate();

        $otherClient = Client::factory()->create();
        $testimonial = Testimonial::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->putJson("/api/public/v1/testimonials/{$testimonial->id}", [
            'description' => 'Updated content',
        ]);

        $response->assertStatus(404);
    }

    public function test_pagination_parameters(): void
    {
        $this->authenticate();

        Testimonial::factory()->count(25)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/testimonials?per_page=10');

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
        Testimonial::factory()->count(3)->create([
            'client_id' => $otherClient->id,
        ]);

        Testimonial::factory()->count(2)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/public/v1/testimonials');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $testimonial) {
            $this->assertEquals($this->client->id, $testimonial['client_id']);
        }
    }

    public function test_delete_is_not_allowed(): void
    {
        $this->authenticate();

        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->deleteJson("/api/public/v1/testimonials/{$testimonial->id}");

        $response->assertStatus(405); // Method Not Allowed
    }

    public function test_index_returns_only_pending_testimonials_by_default(): void
    {
        $this->authenticate();

        $pending = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => null,
        ]);

        Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/public/v1/testimonials');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($pending->id, $data[0]['id']);
    }

    public function test_store_creates_unapproved_testimonial(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/testimonials', [
            'description' => 'Great product',
            'rating' => 5,
            'username' => 'happy_customer',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('approved_at'));
        $this->assertDatabaseHas('testimonials', [
            'id' => $response->json('id'),
            'approved_at' => null,
        ]);
    }

    public function test_store_validates_username_is_required(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/testimonials', [
            'description' => 'Great product, but no username supplied',
            'rating' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
        $this->assertDatabaseMissing('testimonials', [
            'description' => 'Great product, but no username supplied',
        ]);
    }

    public function test_update_validates_approved_at_must_be_a_date(): void
    {
        $this->authenticate();

        $testimonial = Testimonial::factory()->create(['client_id' => $this->client->id]);

        $response = $this->patchJson("/api/public/v1/testimonials/{$testimonial->id}", [
            'approved_at' => 'not-a-date',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['approved_at']);
    }
}
