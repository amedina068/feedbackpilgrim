<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Post $post,
        public $isOwner = true,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isOwner) {
            return $this->post->client->mailSender()->applyTo(new MailMessage)
                ->line("Hello there {$notifiable->name},")
                ->line("Your request: {$this->post->title} has been completed.")
                ->action('View post', $notifiable->generateEmailAuthUrl('/posts/'.$this->post->id));
        }

        return $this->post->client->mailSender()->applyTo(new MailMessage)
            ->line("Hello there {$notifiable->name},")
            ->line("The request you were following: {$this->post->title} has been completed.")
            ->action('View post', $notifiable->generateEmailAuthUrl());

    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
