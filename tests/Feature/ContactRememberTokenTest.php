<?php

namespace Tests\Feature;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Contacts\CreateContactTool;
use App\Mcp\Tools\Contacts\GetContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Contacts\UpdateContactTool;
use App\Mcp\Tools\Posts\GetPostTool;
use App\Mcp\Tools\Posts\ListPostsTool;
use App\Mcp\Tools\Testimonials\GetTestimonialTool;
use App\Mcp\Tools\Testimonials\ListTestimonialsTool;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A contact signs in to the public site with the "contact" guard. Its
 * remember_token is a credential, so no API response or MCP tool result
 * contains it.
 */
class ContactRememberTokenTest extends TestCase
{
    use RefreshDatabase;

    private const REMEMBER_TOKEN = 'remember-token-value-for-the-test';

    private User $user;

    private Client $client;

    private Contact $contact;

    private Post $post;

    private Testimonial $testimonial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);

        $this->contact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'email' => 'jane@example.com',
        ]);
        $this->contact->forceFill(['remember_token' => self::REMEMBER_TOKEN])->save();

        $this->post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $this->testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
        ]);
    }

    private function assertHasNoRememberToken(string $content): void
    {
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString(self::REMEMBER_TOKEN, $content);
    }

    public function test_the_contact_model_hides_the_remember_token(): void
    {
        $this->assertArrayNotHasKey('remember_token', $this->contact->fresh()->toArray());
    }

    public function test_no_public_api_response_contains_the_remember_token(): void
    {
        $token = $this->user->createToken('api', ['*']);
        $token->accessToken->forceFill(['client_id' => $this->client->id])->save();
        $this->withToken($token->plainTextToken);

        $responses = [
            $this->getJson('/api/public/v1/contacts'),
            $this->getJson("/api/public/v1/contacts/{$this->contact->id}"),
            $this->putJson("/api/public/v1/contacts/{$this->contact->id}", ['name' => 'Jane Doe']),
            $this->postJson('/api/public/v1/contacts', ['name' => 'Jane', 'email' => 'jane@example.com']),
            $this->getJson('/api/public/v1/posts'),
            $this->getJson("/api/public/v1/posts/{$this->post->id}"),
            $this->getJson('/api/public/v1/testimonials'),
            $this->getJson("/api/public/v1/testimonials/{$this->testimonial->id}"),
        ];

        foreach ($responses as $response) {
            $response->assertSuccessful();
            $this->assertHasNoRememberToken($response->getContent());
        }
    }

    public function test_no_mcp_tool_result_contains_the_remember_token(): void
    {
        $token = $this->user->createToken('mcp', ['*'])->accessToken;
        $token->forceFill(['client_id' => $this->client->id])->save();
        $this->user->withAccessToken($token);

        $calls = [
            [ListContactsTool::class, []],
            [GetContactTool::class, ['id' => $this->contact->id]],
            [UpdateContactTool::class, ['id' => $this->contact->id, 'name' => 'Jane Doe']],
            [CreateContactTool::class, ['name' => 'Jane', 'email' => 'jane@example.com']],
            [ListPostsTool::class, []],
            [GetPostTool::class, ['id' => $this->post->id]],
            [ListTestimonialsTool::class, []],
            [GetTestimonialTool::class, ['id' => $this->testimonial->id]],
        ];

        foreach ($calls as [$tool, $arguments]) {
            PublicServer::actingAs($this->user)->tool($tool, $arguments)
                ->assertOk()
                ->assertDontSee('remember_token')
                ->assertDontSee(self::REMEMBER_TOKEN);
        }
    }
}
