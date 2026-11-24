<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\User;
use App\Notifications\PostCompletedNotification;
use App\Notifications\PostStatusChangedNotification;
use App\Support\PostStageTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VoterNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Client, 1: Post, 2: Contact, 3: Contact}
     */
    private function postWithAudience(): array
    {
        $client = Client::factory()->create();
        $open = $client->stages()->ofType(Stage::TYPE_OPEN)->orderBy('position')->first();

        $author = Contact::factory()->create(['client_id' => $client->id]);
        $voter = Contact::factory()->create(['client_id' => $client->id]);

        $post = Post::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $author->id,
            'stage_id' => $open->id,
        ]);
        $post->votes()->create(['contact_id' => $voter->id]);

        return [$client, $post, $author, $voter];
    }

    public function test_moving_to_a_non_complete_stage_notifies_author_and_voters(): void
    {
        Notification::fake();

        [$client, $post, $author, $voter] = $this->postWithAudience();
        $planned = $client->stages()->ofType(Stage::TYPE_PLANNED)->first();

        PostStageTransition::apply($post, $planned);

        Notification::assertSentTo($author, PostStatusChangedNotification::class);
        Notification::assertSentTo($voter, PostStatusChangedNotification::class);
    }

    public function test_completion_sends_completed_notification_not_status_changed(): void
    {
        Notification::fake();

        [$client, $post, $author] = $this->postWithAudience();
        $complete = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();

        PostStageTransition::apply($post, $complete);

        Notification::assertSentTo($author, PostCompletedNotification::class);
        Notification::assertNotSentTo($author, PostStatusChangedNotification::class);
    }

    public function test_no_notification_when_stage_is_unchanged(): void
    {
        Notification::fake();

        [$client, $post] = $this->postWithAudience();

        PostStageTransition::apply($post, $post->stage);

        Notification::assertNothingSent();
    }

    public function test_voter_notified_through_the_status_route(): void
    {
        Notification::fake();

        [$client, $post, $author, $voter] = $this->postWithAudience();
        $planned = $client->stages()->ofType(Stage::TYPE_PLANNED)->first();

        $user = User::factory()->create();
        $user->clients()->attach($client);

        $this->actingAs($user)
            ->patch("/posts/{$post->id}/status", ['stage_id' => $planned->id])
            ->assertRedirect("posts/{$post->id}");

        Notification::assertSentTo($voter, PostStatusChangedNotification::class);
    }
}
