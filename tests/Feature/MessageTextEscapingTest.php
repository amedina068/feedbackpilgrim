<?php

namespace Tests\Feature;

use App\Jobs\SendSlackMessage;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostCompletedNotification;
use App\Notifications\PostCreatedByContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A contact of the public site writes the post title and the contact name.
 * This text goes into emails and Slack messages. It must stay plain text, so
 * that a contact cannot put a link or a Slack mention in a message.
 */
class MessageTextEscapingTest extends TestCase
{
    use RefreshDatabase;

    private const LINK_TITLE = '[Reset your password](https://evil.example/reset)';

    private function makePost(Client $client, string $title, string $contactName = 'Jane'): Post
    {
        $contact = Contact::factory()->create(['client_id' => $client->id, 'name' => $contactName]);

        return Post::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'title' => $title,
        ]);
    }

    public function test_a_markdown_link_in_a_post_title_is_not_a_link_in_the_admin_email(): void
    {
        $client = Client::factory()->create();
        $post = $this->makePost($client, self::LINK_TITLE, '<https://evil.example/name>');
        $user = User::factory()->create();

        $html = (string) (new PostCreatedByContact($post))->toMail($user)->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringContainsString('Reset your password', $html);
    }

    public function test_a_markdown_link_in_a_post_title_is_not_a_link_in_the_voter_email(): void
    {
        $client = Client::factory()->create();
        $post = $this->makePost($client, self::LINK_TITLE);
        $voter = Contact::factory()->create(['client_id' => $client->id]);

        $html = (string) (new PostCompletedNotification($post, false))->toMail($voter)->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringContainsString('Reset your password', $html);
    }

    public function test_the_action_button_of_an_email_still_links(): void
    {
        $client = Client::factory()->create();
        $post = $this->makePost($client, 'Dark mode');
        $voter = Contact::factory()->create(['client_id' => $client->id]);

        $html = (string) (new PostCompletedNotification($post, false))->toMail($voter)->render();

        $this->assertStringContainsString('href="'.$client->public_site.'/authenticate/', $html);
    }

    public function test_a_post_title_cannot_add_a_mention_or_a_link_to_a_slack_message(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $client->settings()->create([
            'name' => ClientSetting::NAME_SLACK_WEBHOOK_URL,
            'value' => 'https://hooks.slack.com/services/T000/B000/xxx',
        ]);

        $this->makePost($client, '<!channel> <https://evil.example|Click> & more');

        Queue::assertPushed(SendSlackMessage::class, function (SendSlackMessage $job): bool {
            return ! str_contains($job->text, '<!channel>')
                && ! str_contains($job->text, '<https://evil.example')
                && str_contains($job->text, '&lt;!channel&gt; &lt;https://evil.example|Click&gt; &amp; more');
        });
    }
}
