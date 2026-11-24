<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch a real Redis server: it can hold the data of other apps.
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('set')->andReturn(true);

        // The sign-in link holds an expiry time. Compare links made in the same second.
        $this->freezeTime();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/public/v1/init');

        $response->assertStatus(401);
    }

    public function test_authenticated_request_without_contact_returns_empty_response(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $response = $this->postJson('/api/public/v1/init');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_request_with_new_contact_creates_contact_and_returns_auth_url(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $contactData = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'mrr' => 100.00,
            'ltv' => 1200.00,
            'avatar' => 'https://example.com/avatar.jpg',
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $contactData,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'auth_url',
            ]);

        $this->assertDatabaseHas('contacts', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'client_id' => $this->client->id,
            'mrr' => 100.00,
            'ltv' => 1200.00,
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $contact = Contact::where('email', 'test@example.com')->first();
        $expectedAuthUrl = $contact->generateAuthUrl();

        $response->assertJson([
            'auth_url' => $expectedAuthUrl,
        ]);
    }

    public function test_request_with_existing_contact_updates_contact_and_returns_auth_url(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        // Create existing contact
        $existingContact = Contact::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Old Name',
            'client_id' => $this->client->id,
            'mrr' => 50.00,
            'ltv' => 600.00,
        ]);

        $updatedContactData = [
            'email' => 'test@example.com',
            'name' => 'Updated Name',
            'mrr' => 150.00,
            'ltv' => 1800.00,
            'avatar' => 'https://example.com/new-avatar.jpg',
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $updatedContactData,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'auth_url',
            ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $existingContact->id,
            'email' => 'test@example.com',
            'name' => 'Updated Name',
            'client_id' => $this->client->id,
            'mrr' => 150.00,
            'ltv' => 1800.00,
            'avatar' => 'https://example.com/new-avatar.jpg',
        ]);

        $contact = Contact::find($existingContact->id);
        $expectedAuthUrl = $contact->generateAuthUrl();

        $response->assertJson([
            'auth_url' => $expectedAuthUrl,
        ]);
    }

    public function test_request_with_nested_contact_data_structure(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $contactData = [
            'contact' => [
                'email' => 'nested@example.com',
                'name' => 'Nested User',
                'mrr' => 200.00,
            ],
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $contactData,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'auth_url',
            ]);

        $this->assertDatabaseHas('contacts', [
            'email' => 'nested@example.com',
            'name' => 'Nested User',
            'client_id' => $this->client->id,
            'mrr' => 200.00,
        ]);
    }

    public function test_contact_metadata_is_properly_stored(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $contactData = [
            'email' => 'metadata@example.com',
            'name' => 'Metadata User',
            'company' => 'Test Company',
            'plan' => 'premium',
            'custom_field' => 'custom_value',
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $contactData,
        ]);

        $response->assertStatus(200);

        $contact = Contact::where('email', 'metadata@example.com')->first();
        $metadata = json_decode($contact->metadata, true);

        $this->assertArrayHasKey('company', $metadata);
        $this->assertArrayHasKey('plan', $metadata);
        $this->assertArrayHasKey('custom_field', $metadata);
        $this->assertEquals('Test Company', $metadata['company']);
        $this->assertEquals('premium', $metadata['plan']);
        $this->assertEquals('custom_value', $metadata['custom_field']);
    }

    public function test_login_activity_is_logged(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $contactData = [
            'email' => 'activity@example.com',
            'name' => 'Activity User',
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $contactData,
        ]);

        $response->assertStatus(200);

        $contact = Contact::where('email', 'activity@example.com')->first();

        $this->assertDatabaseHas('activity_log', [
            'client_id' => $this->client->id,
            'description' => 'login',
            'causer_id' => $contact->id,
            'causer_type' => Contact::class,
        ]);

        // Check that login timestamp is stored in metadata
        $metadata = json_decode($contact->metadata, true);
        $this->assertArrayHasKey('pilgrim_last_login', $metadata);
    }

    public function test_login_timestamp_is_stored_in_metadata(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $contactData = [
            'email' => 'timestamp@example.com',
            'name' => 'Timestamp User',
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $contactData,
        ]);

        $response->assertStatus(200);

        $contact = Contact::where('email', 'timestamp@example.com')->first();
        $metadata = json_decode($contact->metadata, true);

        $this->assertArrayHasKey('pilgrim_last_login', $metadata);
        $this->assertNotEmpty($metadata['pilgrim_last_login']);
    }

    public function test_request_with_different_client_works(): void
    {
        $otherClient = Client::factory()->create();
        $this->user->clients()->attach($otherClient);
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $otherClient->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => [
                'email' => 'test@example.com',
                'name' => 'Test User',
            ],
        ]);

        // This should work fine with the other client
        $response->assertStatus(200)
            ->assertJsonStructure(['auth_url']);

        $this->assertDatabaseHas('contacts', [
            'email' => 'test@example.com',
            'client_id' => $otherClient->id,
        ]);
    }

    public function test_contact_with_minimal_data(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $contactData = [
            'email' => 'minimal@example.com',
        ];

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => $contactData,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'auth_url',
            ]);

        $this->assertDatabaseHas('contacts', [
            'email' => 'minimal@example.com',
            'name' => '',
            'client_id' => $this->client->id,
            'mrr' => 0,
            'ltv' => 0,
            'avatar' => '',
        ]);
    }

    public function test_empty_contact_parameter_does_not_create_contact(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => [],
        ]);

        $response->assertStatus(200)
            ->assertJson([]);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_route_has_required_middleware(): void
    {
        // This test verifies that required middleware is applied to the route
        $routes = app('router')->getRoutes();
        $found = false;

        foreach ($routes as $route) {
            if ($route->getActionName() === 'App\Http\Controllers\Api\V1\Public\InitController@index') {
                $middlewares = $route->middleware();

                // Check for auth:sanctum and api_client middleware which are more important for this endpoint
                $this->assertContains('auth:sanctum', $middlewares, 'Sanctum auth middleware should be applied');
                $this->assertContains('api_client', $middlewares, 'API client middleware should be applied');
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Route should exist');
    }

    public function test_contact_without_email_does_not_create_contact(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $response = $this->postJson('/api/public/v1/init', [
            'contact' => ['name' => 'No Email'],
        ]);

        $response->assertStatus(200)
            ->assertJson([]);
        $this->assertDatabaseCount('contacts', 0);
    }
}
