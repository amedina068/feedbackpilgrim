<?php

namespace App\Support;

use App\Jobs\SendSlackMessage;
use App\Models\Client;

class SlackNotifier
{
    /**
     * Escape text that a user wrote, so that Slack shows it as plain text.
     * Slack reads `<...>` as a link or a mention, and `&` starts an entity.
     */
    public static function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    /**
     * Queue a Slack message to a client's configured incoming webhook, if any.
     */
    public static function notify(?int $clientId, string $text): void
    {
        if (! $clientId) {
            return;
        }

        $webhookUrl = Client::query()->whereKey($clientId)->first()?->slack_webhook_url;

        if (! $webhookUrl) {
            return;
        }

        SendSlackMessage::dispatch($webhookUrl, $text);
    }
}
