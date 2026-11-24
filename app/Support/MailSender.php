<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mailer, the From address and the From name for an email to a client's contacts.
 * A null mailer means the default mailer.
 */
final readonly class MailSender
{
    public function __construct(
        public ?string $mailer,
        public string $address,
        public string $name,
    ) {}

    /**
     * Set this mailer and this From address on a notification mail message.
     */
    public function applyTo(MailMessage $message): MailMessage
    {
        return $message->mailer($this->mailer)->from($this->address, $this->name);
    }
}
