<?php

namespace Tests\Feature\SendingDomains;

use App\Events\PostCompletedEvent;
use App\Listeners\RequestTestimonial;
use App\Mail\TestimonialRequest;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use App\Models\SendingDomain;
use App\Notifications\PostCommented;
use App\Notifications\PostCompletedNotification;
use App\Notifications\PostStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The 4 emails to a client's contacts use the client sender.
 * Notifications are checked through toMail(), because Notification::fake() does not
 * expose the mailer or from address of the returned MailMessage.
 */
class ClientSenderEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_keep_the_default_sender_without_a_verified_domain(): void
    {
        $client = Client::factory()->create();
        SendingDomain::factory()->create(['client_id' => $client->id]);

        foreach ($this->mailMessagesFor($client) as $label => $message) {
            $this->assertNull($message->mailer, $label);
            $this->assertSame([config('mail.from.address'), $client->name], $message->from, $label);
        }
    }

    public function test_notifications_use_ses_and_the_client_address_with_a_verified_domain(): void
    {
        $client = Client::factory()->create();
        SendingDomain::factory()->verified()->create([
            'client_id' => $client->id,
            'domain' => 'acme.com',
            'sender_local_part' => 'hello',
        ]);

        foreach ($this->mailMessagesFor($client) as $label => $message) {
            $this->assertSame('ses', $message->mailer, $label);
            $this->assertSame(['hello@acme.com', $client->name], $message->from, $label);
        }
    }

    public function test_testimonial_request_keeps_todays_envelope_without_a_verified_domain(): void
    {
        Mail::fake();
        $client = Client::factory()->create();

        $this->requestTestimonial($client);

        Mail::assertSent(TestimonialRequest::class, function (TestimonialRequest $mail): bool {
            return $mail->mailer === null
                && $mail->from === []
                && $mail->envelope()->from === null;
        });
    }

    public function test_testimonial_request_uses_ses_and_the_client_address_with_a_verified_domain(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        SendingDomain::factory()->verified()->create([
            'client_id' => $client->id,
            'domain' => 'acme.com',
            'sender_local_part' => 'hello',
        ]);

        $this->requestTestimonial($client);

        Mail::assertSent(TestimonialRequest::class, function (TestimonialRequest $mail) use ($client): bool {
            $mail->assertFrom('hello@acme.com', $client->name);

            return $mail->usesMailer('ses');
        });
    }

    public function test_testimonial_request_leaves_through_the_ses_mailer_without_the_fake(): void
    {
        config(['mail.mailers.ses' => ['transport' => 'array']]);
        $client = Client::factory()->create();
        SendingDomain::factory()->verified()->create([
            'client_id' => $client->id,
            'domain' => 'acme.com',
            'sender_local_part' => 'hello',
        ]);

        $this->requestTestimonial($client);

        $messages = Mail::mailer('ses')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('hello@acme.com', $messages->first()->getOriginalMessage()->getFrom()[0]->getAddress());
    }

    /**
     * @return array<string, MailMessage>
     */
    private function mailMessagesFor(Client $client): array
    {
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'contact_id' => $contact->id]);
        $stage = $client->stages()->first();
        $comment = Comment::factory()->create([
            'contact_id' => $contact->id,
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
        ]);

        return [
            'status changed' => (new PostStatusChangedNotification($post, $stage))->toMail($contact),
            'completed, owner' => (new PostCompletedNotification($post))->toMail($contact),
            'completed, voter' => (new PostCompletedNotification($post, false))->toMail($contact),
            'commented' => (new PostCommented($post, $comment))->toMail($contact),
        ];
    }

    private function requestTestimonial(Client $client): void
    {
        $client->settings()->testimonialAutomation()->update(['value' => '1']);
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'contact_id' => $contact->id]);

        (new RequestTestimonial)->handle(new PostCompletedEvent($post));
    }
}
