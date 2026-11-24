<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\User;
use App\Models\Webhook;
use App\Support\PostStageTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['example.test' => ['93.184.216.34']]);
    }

    public function test_webhooks_settings_page_renders(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);
        Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://example.test/hook']);

        $this->actingAs($user)
            ->get('/settings/webhooks')
            ->assertOk()
            ->assertSee('Webhooks')
            ->assertSee('https://example.test/hook');
    }

    public function test_post_created_queues_delivery_to_subscribed_webhooks_only(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $subscribed = Webhook::factory()->create([
            'client_id' => $client->id,
            'events' => [Webhook::EVENT_POST_CREATED],
        ]);
        Webhook::factory()->create([
            'client_id' => $client->id,
            'events' => [Webhook::EVENT_POST_COMPLETED],
        ]);

        Post::factory()->create(['client_id' => $client->id]);

        Queue::assertPushed(DeliverWebhook::class, 1);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->webhook->is($subscribed)
            && $job->event === Webhook::EVENT_POST_CREATED);
    }

    public function test_inactive_and_other_client_webhooks_are_skipped(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        Webhook::factory()->inactive()->create(['client_id' => $client->id, 'events' => [Webhook::EVENT_POST_CREATED]]);
        Webhook::factory()->create(['client_id' => Client::factory()->create()->id, 'events' => [Webhook::EVENT_POST_CREATED]]);

        Post::factory()->create(['client_id' => $client->id]);

        Queue::assertNotPushed(DeliverWebhook::class);
    }

    public function test_status_change_and_completion_emit_events(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        Webhook::factory()->create([
            'client_id' => $client->id,
            'events' => [Webhook::EVENT_POST_STATUS_CHANGED, Webhook::EVENT_POST_COMPLETED],
        ]);
        $complete = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();
        $post = Post::factory()->create(['client_id' => $client->id, 'status' => Post::STATUS_PENDING]);

        Queue::fake(); // reset; ignore the create event

        PostStageTransition::apply($post, $complete);

        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->event === Webhook::EVENT_POST_STATUS_CHANGED);
        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->event === Webhook::EVENT_POST_COMPLETED);
    }

    public function test_delivery_records_a_successful_attempt(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://example.test/hook']);

        (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 1]))->handle();

        $delivery = $webhook->deliveries()->first();
        $this->assertNotNull($delivery);
        $this->assertTrue($delivery->success);
        $this->assertSame(200, $delivery->status_code);
        $this->assertSame(Webhook::EVENT_POST_CREATED, $delivery->event);
    }

    public function test_delivery_records_a_failed_attempt_and_rethrows(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://example.test/hook']);

        try {
            (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 1]))->handle();
            $this->fail('Expected a RequestException.');
        } catch (RequestException $e) {
            // expected — drives the job retry
        }

        $delivery = $webhook->deliveries()->first();
        $this->assertNotNull($delivery);
        $this->assertFalse($delivery->success);
        $this->assertSame(500, $delivery->status_code);
    }

    public function test_delivery_sends_signed_request(): void
    {
        Http::fake();

        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create([
            'client_id' => $client->id,
            'url' => 'https://example.test/hook',
            'secret' => 'shhh-secret',
        ]);

        (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 7]))->handle();

        Http::assertSent(function ($request) {
            $expected = hash_hmac('sha256', $request->body(), 'shhh-secret');

            return $request->url() === 'https://example.test/hook'
                && $request->hasHeader('X-FeedbackPilgrim-Event', Webhook::EVENT_POST_CREATED)
                && $request->hasHeader('X-FeedbackPilgrim-Signature', $expected)
                && str_contains($request->body(), '"event":"post.created"');
        });
    }

    public function test_delivery_signs_the_timestamp_and_the_body(): void
    {
        Http::fake();
        $this->travelTo(now()->setTimestamp(1_763_456_789));

        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create([
            'client_id' => $client->id,
            'url' => 'https://example.test/hook',
            'secret' => 'shhh-secret',
        ]);

        (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 7]))->handle();

        Http::assertSent(function ($request) {
            $expected = hash_hmac('sha256', '1763456789.'.$request->body(), 'shhh-secret');

            return $request->hasHeader('X-FeedbackPilgrim-Timestamp', '1763456789')
                && $request->hasHeader('X-FeedbackPilgrim-Signature-V2', $expected);
        });
    }

    public function test_each_attempt_of_a_delivery_sends_the_same_delivery_id(): void
    {
        Http::fake(['*' => Http::sequence()->push('boom', 500)->push('ok', 200)]);

        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://example.test/hook']);
        $job = new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 7]);

        try {
            $job->handle();
        } catch (RequestException) {
            // The queue retries the job from its serialized copy.
        }

        unserialize(serialize($job))->handle();

        $deliveryIds = Http::recorded()->map(fn (array $pair) => $pair[0]->header('X-FeedbackPilgrim-Delivery')[0] ?? null);

        $this->assertCount(2, $deliveryIds);
        $this->assertTrue(Str::isUuid($deliveryIds[0]));
        $this->assertSame($deliveryIds[0], $deliveryIds[1]);
        $this->assertNotSame($job->deliveryId, (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 7]))->deliveryId);
    }
}
