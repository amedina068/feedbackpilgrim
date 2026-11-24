<?php

namespace App\Support;

use App\Events\PostCompletedEvent;
use App\Models\Post;
use App\Models\Stage;
use App\Models\Webhook;
use App\Notifications\PostCompletedNotification;
use App\Notifications\PostStatusChangedNotification;
use Illuminate\Notifications\Notification;

class PostStageTransition
{
    /**
     * Move a post to a stage (optionally repositioning it). When the stage actually
     * changes, notify the author + voters: a richer "completed" notification (plus the
     * PostCompletedEvent) when it newly enters a complete-type stage, otherwise a generic
     * status-change notification.
     */
    public static function apply(Post $post, Stage $stage, ?int $position = null): void
    {
        $wasComplete = $post->completed();
        $previousStageId = $post->stage_id;

        $post->stage_id = $stage->id;

        if ($position !== null) {
            $post->stage_position = $position;
        }

        $post->save();

        if ((int) $previousStageId === (int) $stage->id) {
            return;
        }

        WebhookDispatcher::dispatch($post->client_id, Webhook::EVENT_POST_STATUS_CHANGED, $post->toWebhookPayload());

        SlackNotifier::notify($post->client_id, '🔄 *'.SlackNotifier::escape($post->title).'* moved to *'.SlackNotifier::escape($stage->name)."*\n{$post->path}");

        if (! $wasComplete && $stage->isComplete()) {
            static::notifyAudience(
                $post,
                fn ($isOwner) => new PostCompletedNotification($post, isOwner: $isOwner),
            );

            WebhookDispatcher::dispatch($post->client_id, Webhook::EVENT_POST_COMPLETED, $post->toWebhookPayload());

            PostCompletedEvent::dispatch($post);

            return;
        }

        static::notifyAudience(
            $post,
            fn ($isOwner) => new PostStatusChangedNotification($post, $stage, isOwner: $isOwner),
        );
    }

    /**
     * Notify the post author and every unique voter (excluding the author).
     *
     * @param  callable(bool): Notification  $factory
     */
    private static function notifyAudience(Post $post, callable $factory): void
    {
        $post->contact?->notify($factory(true));

        $post->votes()
            ->when($post->contact, fn ($query) => $query->where('contact_id', '!=', $post->contact->id))
            ->with(['contact'])
            ->get()
            ->unique('contact_id')
            ->each(fn ($vote) => $vote->contact?->notify($factory(false)));
    }
}
