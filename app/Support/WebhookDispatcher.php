<?php

namespace App\Support;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;

class WebhookDispatcher
{
    /**
     * Queue delivery of an event to every active webhook of a client subscribed to it.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatch(?int $clientId, string $event, array $payload): void
    {
        if (! $clientId) {
            return;
        }

        Webhook::query()
            ->where('client_id', $clientId)
            ->active()
            ->get()
            ->filter(fn (Webhook $webhook) => $webhook->subscribesTo($event))
            ->each(fn (Webhook $webhook) => DeliverWebhook::dispatch($webhook, $event, $payload));
    }
}
