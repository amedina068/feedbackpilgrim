<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Posts\CreatePostCommentTool;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostCommented;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PostCommentToolTest extends TestCase
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

    private function callTool(array $args = [])
    {
        return PublicServer::actingAs($this->user)->tool(CreatePostCommentTool::class, $args);
    }

    public function test_comment_is_attributed_to_the_token_users_admin_contact(): void
    {
        $adminContact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
        ]);
        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $this->callTool(['post_id' => $post->id, 'content' => 'On it.'])
            ->assertOk()
            ->assertSee('On it.');

        $this->assertDatabaseHas('comments', [
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
            'contact_id' => $adminContact->id,
            'content' => 'On it.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'client_id' => $this->client->id,
            'description' => 'comment_created',
            'causer_id' => $adminContact->id,
            'causer_type' => Contact::class,
            'subject_id' => $post->id,
            'subject_type' => Post::class,
        ]);
    }

    public function test_admin_contact_is_created_when_missing_for_the_client(): void
    {
        $otherClient = Client::factory()->create();
        Contact::factory()->create(['client_id' => $otherClient->id, 'user_id' => $this->user->id]);
        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $this->callTool(['post_id' => $post->id, 'content' => 'Hello'])->assertOk();

        $this->assertDatabaseHas('contacts', [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'email' => $this->user->email,
        ]);

        $contact = Contact::query()->where('client_id', $this->client->id)->where('user_id', $this->user->id)->firstOrFail();
        $this->assertDatabaseHas('comments', ['commentable_id' => $post->id, 'contact_id' => $contact->id]);
    }

    public function test_post_author_is_notified(): void
    {
        Notification::fake();

        $author = Contact::factory()->create(['client_id' => $this->client->id]);
        $post = Post::factory()->create(['client_id' => $this->client->id, 'contact_id' => $author->id]);

        $this->callTool(['post_id' => $post->id, 'content' => 'Reply'])->assertOk();

        Notification::assertSentTo($author, PostCommented::class);
    }

    public function test_post_without_author_does_not_fail(): void
    {
        Notification::fake();

        $post = Post::factory()->create(['client_id' => $this->client->id, 'contact_id' => null]);

        $this->callTool(['post_id' => $post->id, 'content' => 'Reply'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_post_for_other_client_is_not_found(): void
    {
        $foreign = Post::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(['post_id' => $foreign->id, 'content' => 'Hijack'])
            ->assertHasErrors();

        $this->assertDatabaseMissing('comments', ['commentable_id' => $foreign->id]);
    }

    public function test_missing_content_is_a_structured_error(): void
    {
        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $this->callTool(['post_id' => $post->id])->assertHasErrors();

        $this->assertDatabaseMissing('comments', ['commentable_id' => $post->id]);
    }
}
