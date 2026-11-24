<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Contacts\CreateContactTool;
use App\Mcp\Tools\Contacts\UpdateContactTool;
use App\Mcp\Tools\Posts\CreatePostTool;
use App\Mcp\Tools\Posts\UpdatePostTool;
use App\Mcp\Tools\ReleaseNotes\CreateReleaseNoteTool;
use App\Mcp\Tools\ReleaseNotes\UpdateReleaseNoteTool;
use App\Mcp\Tools\Testimonials\CreateTestimonialTool;
use App\Mcp\Tools\Testimonials\UpdateTestimonialTool;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriteToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);

        $token = $this->user->createToken('mcp', ['*'])->accessToken;
        $token->client_id = $this->client->id;
        $token->save();

        $this->user->withAccessToken($token);
    }

    private function callTool(string $tool, array $args = [])
    {
        return PublicServer::actingAs($this->user)->tool($tool, $args);
    }

    public function test_create_post_persists_scoped(): void
    {
        $this->callTool(CreatePostTool::class, ['title' => 'Tooled', 'content' => 'Body'])
            ->assertOk()
            ->assertSee('Tooled');

        $this->assertDatabaseHas('posts', ['title' => 'Tooled', 'client_id' => $this->client->id]);
    }

    public function test_create_post_ignores_client_id_param(): void
    {
        $other = Client::factory()->create();

        $this->callTool(CreatePostTool::class, [
            'title' => 'Scoped',
            'content' => 'Body',
            'client_id' => $other->id,
        ])->assertOk();

        $this->assertDatabaseHas('posts', ['title' => 'Scoped', 'client_id' => $this->client->id]);
        $this->assertDatabaseMissing('posts', ['title' => 'Scoped', 'client_id' => $other->id]);
    }

    public function test_create_post_validation_error_is_structured(): void
    {
        $this->callTool(CreatePostTool::class, ['content' => 'No title'])
            ->assertHasErrors();
    }

    public function test_create_post_with_cross_client_tag_is_rejected(): void
    {
        $foreignTag = Tag::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(CreatePostTool::class, [
            'title' => 'Tagged',
            'content' => 'Body',
            'tags' => [$foreignTag->id],
        ])->assertHasErrors();
    }

    public function test_update_post_persists_changes(): void
    {
        $post = Post::factory()->create(['client_id' => $this->client->id, 'title' => 'Before', 'status' => 'pending']);

        $this->callTool(UpdatePostTool::class, ['id' => $post->id, 'title' => 'After', 'status' => 'completed'])
            ->assertOk()
            ->assertSee('After');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'After', 'status' => 'completed']);
    }

    public function test_update_post_for_other_client_is_not_found(): void
    {
        $foreign = Post::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(UpdatePostTool::class, ['id' => $foreign->id, 'title' => 'Hijack'])
            ->assertHasErrors();

        $this->assertDatabaseMissing('posts', ['id' => $foreign->id, 'title' => 'Hijack']);
    }

    public function test_update_release_note_persists_changes(): void
    {
        $note = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'title' => 'Old Note']);

        $this->callTool(UpdateReleaseNoteTool::class, ['id' => $note->id, 'title' => 'New Note'])
            ->assertOk()
            ->assertSee('New Note');

        $this->assertDatabaseHas('release_notes', ['id' => $note->id, 'title' => 'New Note']);
    }

    public function test_update_release_note_for_other_client_is_not_found(): void
    {
        $foreign = ReleaseNote::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(UpdateReleaseNoteTool::class, ['id' => $foreign->id, 'title' => 'Hijack'])
            ->assertHasErrors();
    }

    public function test_update_testimonial_persists_approved_at(): void
    {
        $testimonial = Testimonial::factory()->create(['client_id' => $this->client->id, 'approved_at' => null]);

        $this->callTool(UpdateTestimonialTool::class, ['id' => $testimonial->id, 'approved_at' => '2026-06-16T00:00:00Z'])
            ->assertOk();

        $this->assertNotNull($testimonial->fresh()->approved_at);
    }

    public function test_update_testimonial_for_other_client_is_not_found(): void
    {
        $foreign = Testimonial::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(UpdateTestimonialTool::class, ['id' => $foreign->id, 'username' => 'hijack'])
            ->assertHasErrors();
    }

    public function test_create_release_note_validation_error_is_structured(): void
    {
        $this->callTool(CreateReleaseNoteTool::class, ['title' => 'No content'])
            ->assertHasErrors();
    }

    public function test_create_testimonial_validation_error_is_structured(): void
    {
        $this->callTool(CreateTestimonialTool::class, ['rating' => 5])
            ->assertHasErrors();
    }

    public function test_create_contact_surfaces_was_created(): void
    {
        $this->callTool(CreateContactTool::class, ['name' => 'Jane', 'email' => 'jane@acme.com'])
            ->assertOk()
            ->assertSee('"was_created":true');

        $this->callTool(CreateContactTool::class, ['name' => 'Jane 2', 'email' => 'jane@acme.com'])
            ->assertOk()
            ->assertSee('"was_created":false');
    }

    public function test_create_contact_stores_custom_metadata_field(): void
    {
        $this->callTool(CreateContactTool::class, [
            'name' => 'Meta',
            'email' => 'meta@acme.com',
            'plan' => 'enterprise',
        ])->assertOk()->assertSee('enterprise');
    }

    public function test_update_contact_for_other_client_is_not_found(): void
    {
        $foreign = Contact::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(UpdateContactTool::class, ['id' => $foreign->id, 'name' => 'Hijack'])
            ->assertHasErrors();
    }

    public function test_create_release_note_persists_as_draft(): void
    {
        $this->callTool(CreateReleaseNoteTool::class, ['title' => 'Launch', 'content' => 'Notes'])
            ->assertOk()
            ->assertSee('Launch');

        $this->assertDatabaseHas('release_notes', [
            'title' => 'Launch',
            'published_at' => null,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_update_release_note_publishes_it(): void
    {
        $note = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'published_at' => null]);

        $this->callTool(UpdateReleaseNoteTool::class, ['id' => $note->id, 'published' => true])->assertOk();

        $this->assertNotNull($note->fresh()->published_at);
    }

    public function test_create_testimonial_persists_scoped(): void
    {
        $this->callTool(CreateTestimonialTool::class, [
            'description' => 'Great',
            'username' => 'fan',
            'rating' => 5,
        ])->assertOk()->assertSee('fan');

        $this->assertDatabaseHas('testimonials', ['username' => 'fan', 'client_id' => $this->client->id]);
    }
}
