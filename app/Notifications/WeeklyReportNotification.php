<?php

namespace App\Notifications;

use App\Models\WeeklyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private WeeklyReport $report
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
        $metrics = $this->report->metrics;
        $clientName = $this->report->client->name;

        return (new MailMessage)
            ->subject("Weekly Report for {$clientName}")
            ->greeting('Hello!')
            ->line("Here's your weekly activity report for {$clientName}.")
            ->line("**Week:** {$this->report->week_start->format('M j')} - {$this->report->week_end->format('M j, Y')}")
            ->line('')
            ->line('**Activity Summary:**')
            ->line("• Posts Created: {$metrics['posts_created']}")
            ->line("• Posts Completed: {$metrics['posts_completed']}")
            ->line("• Active Contacts: {$metrics['active_contacts']}")
            ->line("• Total Engagement Events: {$metrics['total_logins']}")
            ->line('')
            ->when(! empty($metrics['contacts_with_most_posts']), function ($message) use ($metrics) {
                $message->line('**Top Post Creators:**');
                foreach (array_slice($metrics['contacts_with_most_posts'], 0, 3) as $contact) {
                    $message->line("• {$contact['name']} - {$contact['posts_count']} posts");
                }
                $message->line('');
            })
            ->when(! empty($metrics['contacts_with_most_comments']), function ($message) use ($metrics) {
                $message->line('**Most Active Commenters:**');
                foreach (array_slice($metrics['contacts_with_most_comments'], 0, 3) as $contact) {
                    $message->line("• {$contact['name']} - {$contact['comments_count']} comments");
                }
                $message->line('');
            })
            ->action('View Dashboard', url('/dashboard'))
            ->line('Thanks for using Feedback Pilgrim!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'client_id' => $this->report->client_id,
            'week_start' => $this->report->week_start,
            'week_end' => $this->report->week_end,
        ];
    }
}
