<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\Stage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Post $post,
        public Stage $stage,
        public bool $isOwner = true,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->isOwner
            ? "Your request: {$this->post->title} is now \"{$this->stage->name}\"."
            : "A request you voted on: {$this->post->title} is now \"{$this->stage->name}\".";

        return $this->post->client->mailSender()->applyTo(new MailMessage)
            ->subject("Update on \"{$this->post->title}\"")
            ->line("Hello there {$notifiable->name},")
            ->line($lead)
            ->action('View post', $notifiable->generateEmailAuthUrl('/posts/'.$this->post->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'stage_id' => $this->stage->id,
        ];
    }
}
