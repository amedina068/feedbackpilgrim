<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookUrlSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Client}
     */
    private function admin(): array
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return [$user, $client];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function refusedUrls(): array
    {
        return [
            'plain http' => ['http://hooks.example.com/in'],
            'loopback' => ['https://127.0.0.1/in'],
            'private range' => ['https://10.1.2.3/in'],
            'link local metadata' => ['https://169.254.169.254/latest'],
            'carrier grade nat' => ['https://100.64.0.1/in'],
            'ipv6 loopback' => ['https://[::1]/in'],
            'ipv4 mapped ipv6' => ['https://[::ffff:127.0.0.1]/in'],
            'nat64 prefix' => ['https://[64:ff9b::7f00:1]/in'],
            '6to4 prefix' => ['https://[2002:7f00:1::1]/in'],
            'name that resolves to a private address' => ['https://internal.example.com/in'],
            'name that does not resolve' => ['https://nowhere.example.com/in'],
            'credentials in the url' => ['https://user:pass@hooks.example.com/in'],
        ];
    }

    #[DataProvider('refusedUrls')]
    public function test_admin_cannot_save_a_webhook_url_that_is_not_public_https(string $url): void
    {
        $this->fakeDns([
            'hooks.example.com' => ['93.184.216.34'],
            'internal.example.com' => ['93.184.216.34', '192.168.1.10'],
        ]);
        [$user, $client] = $this->admin();

        $this->actingAs($user)
            ->post('/webhooks', ['url' => $url, 'events' => [Webhook::EVENT_POST_CREATED]])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, Webhook::query()->where('client_id', $client->id)->count());
    }

    public function test_admin_can_save_a_public_https_webhook_url(): void
    {
        $this->fakeDns(['hooks.example.com' => ['93.184.216.34']]);
        [$user, $client] = $this->admin();

        $this->actingAs($user)
            ->post('/webhooks', ['url' => 'https://hooks.example.com/in', 'events' => [Webhook::EVENT_POST_CREATED]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Webhook::query()->where('client_id', $client->id)->count());
    }

    public function test_admin_cannot_change_a_webhook_url_to_a_private_address(): void
    {
        $this->fakeDns(['hooks.example.com' => ['93.184.216.34']]);
        [$user, $client] = $this->admin();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://hooks.example.com/in']);

        $this->actingAs($user)
            ->patch('/webhooks/'.$webhook->id, ['url' => 'https://127.0.0.1/in', 'events' => [Webhook::EVENT_POST_CREATED]])
            ->assertSessionHasErrors('url');

        $this->assertSame('https://hooks.example.com/in', $webhook->fresh()->url);
    }

    public function test_delivery_is_refused_when_the_host_now_resolves_to_a_private_address(): void
    {
        Http::fake();
        $this->fakeDns(['hooks.example.com' => ['10.0.0.5']]);
        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://hooks.example.com/in']);

        (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 1]))->handle();

        Http::assertNothingSent();
        $delivery = $webhook->deliveries()->firstOrFail();
        $this->assertFalse($delivery->success);
        $this->assertSame(WebhookDelivery::RESULT_ADDRESS_REFUSED, $delivery->response_body);
    }

    public function test_delivery_pins_the_resolved_address_and_does_not_follow_redirects(): void
    {
        $this->fakeDns(['hooks.example.com' => ['93.184.216.34']]);
        $options = null;
        Http::fake(function ($request, $requestOptions) use (&$options) {
            $options = $requestOptions;

            return Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']);
        });
        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://hooks.example.com/in']);

        (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 1]))->handle();

        Http::assertSentCount(1);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['hooks.example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);

        $delivery = $webhook->deliveries()->firstOrFail();
        $this->assertFalse($delivery->success);
        $this->assertSame(302, $delivery->status_code);
        $this->assertSame(WebhookDelivery::RESULT_REDIRECT_REFUSED, $delivery->response_body);
    }

    public function test_delivery_stores_a_fixed_code_and_not_the_response_body(): void
    {
        $this->fakeDns(['hooks.example.com' => ['93.184.216.34']]);
        Http::fake(['*' => Http::response('internal secret page', 200)]);
        $client = Client::factory()->create();
        $webhook = Webhook::factory()->create(['client_id' => $client->id, 'url' => 'https://hooks.example.com/in']);

        (new DeliverWebhook($webhook, Webhook::EVENT_POST_CREATED, ['id' => 1]))->handle();

        $delivery = $webhook->deliveries()->firstOrFail();
        $this->assertTrue($delivery->success);
        $this->assertSame(WebhookDelivery::RESULT_DELIVERED, $delivery->response_body);
    }
}
