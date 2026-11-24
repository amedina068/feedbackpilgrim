<?php

namespace Tests\Feature\SendingDomains;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use App\Models\SendingDomain;
use App\Notifications\PostCommented;
use App\Support\MailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_name_with_a_line_break_cannot_add_a_header(): void
    {
        config(['mail.default' => 'array']);
        $client = Client::factory()->create(['name' => "Acme\r\nBcc: evil@example.com"]);

        $sender = $client->mailSender();

        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $sender->name);

        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'contact_id' => $contact->id]);
        $comment = Comment::factory()->create([
            'contact_id' => $contact->id,
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
        ]);

        $contact->notifyNow(new PostCommented($post, $comment));

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertFalse($email->getHeaders()->has('Bcc'));
        $this->assertSame([], $email->getBcc());
        $this->assertCount(1, $email->getFrom());
        $this->assertSame(config('mail.from.address'), $email->getFrom()[0]->getAddress());
    }

    public function test_a_verified_domain_sends_through_ses_from_the_client_address(): void
    {
        $domain = SendingDomain::factory()->verified()->create(['domain' => 'acme.com', 'sender_local_part' => 'hello']);

        $sender = $domain->client->mailSender();

        $this->assertInstanceOf(MailSender::class, $sender);
        $this->assertSame('ses', $sender->mailer);
        $this->assertSame('hello@acme.com', $sender->address);
        $this->assertSame($domain->client->name, $sender->name);
    }

    public function test_a_client_without_a_domain_uses_the_default_sender(): void
    {
        $client = Client::factory()->create();

        $this->assertDefaultSender($client);
    }

    public function test_a_pending_domain_uses_the_default_sender(): void
    {
        $this->assertDefaultSender(SendingDomain::factory()->create()->client);
    }

    public function test_a_failed_domain_uses_the_default_sender(): void
    {
        $this->assertDefaultSender(SendingDomain::factory()->failed()->create()->client);
    }

    public function test_a_domain_that_lost_verification_uses_the_default_sender(): void
    {
        $this->assertDefaultSender(SendingDomain::factory()->verificationLost()->create()->client);
    }

    public function test_apply_to_sets_the_mailer_and_the_from_address_on_a_mail_message(): void
    {
        $message = new MailMessage;

        $result = (new MailSender('ses', 'hello@acme.com', 'Acme'))->applyTo($message);

        $this->assertSame($message, $result);
        $this->assertSame('ses', $result->mailer);
        $this->assertSame(['hello@acme.com', 'Acme'], $result->from);
    }

    private function assertDefaultSender(Client $client): void
    {
        $sender = $client->mailSender();

        $this->assertNull($sender->mailer);
        $this->assertSame(config('mail.from.address'), $sender->address);
        $this->assertSame($client->name, $sender->name);
    }
}
