<?php

namespace Tests\Feature;

use App\Jobs\SendSlackMessage;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\User;
use App\Support\PostStageTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SlackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function clientWithSlack(): Client
    {
        $client = Client::factory()->create();
        $client->settings()->create([
            'name' => ClientSetting::NAME_SLACK_WEBHOOK_URL,
            'value' => 'https://hooks.slack.com/services/T000/B000/xxx',
        ]);

        return $client;
    }

    public function test_new_post_queues_a_slack_message_when_configured(): void
    {
        Queue::fake();

        $client = $this->clientWithSlack();
        $post = Post::factory()->create(['client_id' => $client->id, 'title' => 'Dark mode']);

        Queue::assertPushed(SendSlackMessage::class, fn (SendSlackMessage $job) => $job->webhookUrl === 'https://hooks.slack.com/services/T000/B000/xxx'
            && str_contains($job->text, 'Dark mode'));
    }

    public function test_no_slack_message_when_client_has_no_webhook(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        Post::factory()->create(['client_id' => $client->id]);

        Queue::assertNotPushed(SendSlackMessage::class);
    }

    public function test_status_change_queues_a_slack_message(): void
    {
        Queue::fake();

        $client = $this->clientWithSlack();
        $complete = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();
        $post = Post::factory()->create(['client_id' => $client->id, 'status' => Post::STATUS_PENDING, 'title' => 'Export CSV']);

        Queue::fake(); // reset; ignore the create-time message

        PostStageTransition::apply($post, $complete);

        Queue::assertPushed(SendSlackMessage::class, fn (SendSlackMessage $job) => str_contains($job->text, 'Export CSV')
            && str_contains($job->text, $complete->name));
    }

    public function test_job_posts_text_to_webhook(): void
    {
        Http::fake();

        (new SendSlackMessage('https://hooks.slack.com/services/T/B/z', 'hello'))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/T/B/z'
            && $request['text'] === 'hello');
    }

    public function test_admin_can_save_and_clear_the_slack_webhook(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/settings/integrations')
            ->assertOk()
            ->assertSee('Slack');

        $this->actingAs($user)
            ->patch('/settings/integrations', ['slack_webhook_url' => 'https://hooks.slack.com/services/A/B/c'])
            ->assertRedirect();

        $this->assertSame('https://hooks.slack.com/services/A/B/c', $client->fresh()->slack_webhook_url);

        $this->actingAs($user)
            ->patch('/settings/integrations', ['slack_webhook_url' => ''])
            ->assertRedirect();

        $this->assertNull($client->fresh()->slack_webhook_url);
    }

    public function test_rejects_non_slack_webhook_url(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->patch('/settings/integrations', ['slack_webhook_url' => 'https://evil.test/hook'])
            ->assertSessionHasErrors('slack_webhook_url');
    }
}
