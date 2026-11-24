<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Jobs\SendSlackMessage;
use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Posts\UpdatePostTool;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\User;
use App\Models\Webhook;
use App\Notifications\PostCompletedNotification;
use App\Notifications\PostStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostStatusFromApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Contact $author;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);

        $this->client->settings()->create([
            'name' => ClientSetting::NAME_SLACK_WEBHOOK_URL,
            'value' => 'https://hooks.slack.com/services/T000/B000/xxx',
        ]);

        Webhook::factory()->create([
            'client_id' => $this->client->id,
            'events' => [Webhook::EVENT_POST_STATUS_CHANGED, Webhook::EVENT_POST_COMPLETED],
        ]);

        $this->author = Contact::factory()->create(['client_id' => $this->client->id]);
        $this->post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $this->author->id,
            'status' => Post::STATUS_PENDING,
        ]);

        Queue::fake(); // Forget the jobs of the post creation.
        Notification::fake();
    }

    public function test_a_status_change_from_the_rest_api_notifies_like_the_dashboard(): void
    {
        $this->withToken($this->token())
            ->patchJson("/api/public/v1/posts/{$this->post->id}", ['status' => Post::STATUS_IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('status', Post::STATUS_IN_PROGRESS);

        $this->assertSame(Stage::TYPE_ACTIVE, $this->post->fresh()->stage->type);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === Webhook::EVENT_POST_STATUS_CHANGED);
        Queue::assertNotPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === Webhook::EVENT_POST_COMPLETED);
        Queue::assertPushed(SendSlackMessage::class);
        Notification::assertSentTo($this->author, PostStatusChangedNotification::class);
    }

    public function test_a_completed_status_from_mcp_sends_the_completed_notification(): void
    {
        $token = $this->user->createToken('mcp', ['*'])->accessToken;
        $token->forceFill(['client_id' => $this->client->id])->save();

        PublicServer::actingAs($this->user->withAccessToken($token))
            ->tool(UpdatePostTool::class, ['id' => $this->post->id, 'status' => Post::STATUS_COMPLETED])
            ->assertOk();

        $this->assertSame(Stage::TYPE_COMPLETE, $this->post->fresh()->stage->type);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === Webhook::EVENT_POST_STATUS_CHANGED);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === Webhook::EVENT_POST_COMPLETED);
        Notification::assertSentTo($this->author, PostCompletedNotification::class);
    }

    public function test_the_same_status_sends_nothing(): void
    {
        $this->withToken($this->token())
            ->patchJson("/api/public/v1/posts/{$this->post->id}", ['status' => Post::STATUS_PENDING, 'title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('title', 'New title');

        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    private function token(): string
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->forceFill(['client_id' => $this->client->id])->save();

        return $token->plainTextToken;
    }
}
