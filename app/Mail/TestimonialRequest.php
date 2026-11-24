<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestimonialRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $client;

    public $contentBeforeButton;

    public $contentAfterButton;

    public $testimonialUrl;

    public function __construct(
        public Contact $contact
    ) {
        $this->client = $contact->client;
        $this->testimonialUrl = $contact->generateEmailAuthUrl('/testimonials/create');
        $this->processEmailContent();
    }

    /**
     * The greeting of the default body before 2026-11. Bodies that clients
     * saved with it still work.
     */
    private const LEGACY_NAME_PLACEHOLDER = '{{ $contact->name ?? $contact->email }}';

    private function processEmailContent(): void
    {
        $name = $this->contact->name ?: $this->contact->email;

        $emailBody = str_replace(
            ['{name}', self::LEGACY_NAME_PLACEHOLDER],
            $name,
            $this->client->testimonialEmailBody,
        );

        $parts = explode('{testimonialButton}', $emailBody);

        $this->contentBeforeButton = trim($parts[0] ?? '');
        $this->contentAfterButton = trim($parts[1] ?? '');

        if (count($parts) === 1) {
            $this->contentBeforeButton = $emailBody;
            $this->contentAfterButton = '';
        }
    }

    /**
     * Only a verified sending domain sets the From address.
     * Without one, the default From address and name apply, as before.
     */
    public function envelope(): Envelope
    {
        $sender = $this->client->mailSender();

        return new Envelope(
            from: $sender->mailer === null ? null : new Address($sender->address, $sender->name),
            subject: 'Share your experience with us!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.testimonial-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
