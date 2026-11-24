<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\WebhookUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /**
     * The ID that the receiver uses to drop a repeated delivery. It stays the same on each retry.
     */
    public string $deliveryId;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Webhook $webhook,
        public string $event,
        public array $payload,
    ) {
        $this->deliveryId = (string) Str::uuid();
    }

    public function handle(): void
    {
        $address = app(WebhookUrlGuard::class)->publicAddressFor($this->webhook->url);

        if ($address === null) {
            $this->record(null, false, WebhookDelivery::RESULT_ADDRESS_REFUSED);

            return;
        }

        $pin = WebhookUrlGuard::curlResolveEntry($this->webhook->url, $address);

        $body = [
            'event' => $this->event,
            'data' => $this->payload,
        ];

        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-FeedbackPilgrim-Event' => $this->event,
                'X-FeedbackPilgrim-Delivery' => $this->deliveryId,
                'X-FeedbackPilgrim-Timestamp' => $timestamp,
                'X-FeedbackPilgrim-Signature-V2' => $this->sign($timestamp.'.'.$json),
                // Deprecated: receivers that verify only the body read this header. Remove it in the next release.
                'X-FeedbackPilgrim-Signature' => $this->sign($json),
            ])->withBody($json, 'application/json')
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(10)
                ->when($pin !== null, fn ($request) => $request->withOptions([
                    'curl' => [CURLOPT_RESOLVE => [$pin]],
                ]))
                ->post($this->webhook->url);
        } catch (ConnectionException $e) {
            $this->record(null, false, WebhookDelivery::RESULT_CONNECTION_FAILED);

            throw $e;
        }

        $this->record($response->status(), $response->successful(), match (true) {
            $response->successful() => WebhookDelivery::RESULT_DELIVERED,
            $response->redirect() => WebhookDelivery::RESULT_REDIRECT_REFUSED,
            default => WebhookDelivery::RESULT_HTTP_ERROR,
        });

        $response->throw();
    }

    private function sign(string $message): string
    {
        return hash_hmac('sha256', $message, $this->webhook->secret);
    }

    private function record(?int $statusCode, bool $success, string $result): void
    {
        $this->webhook->deliveries()->create([
            'event' => $this->event,
            'status_code' => $statusCode,
            'success' => $success,
            'response_body' => $result,
        ]);
    }
}
