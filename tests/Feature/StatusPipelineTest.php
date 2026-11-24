<?php

namespace Tests\Feature;

use App\Events\PostCompletedEvent;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\User;
use App\Notifications\PostCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StatusPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function clientWithDefaults(): Client
    {
        return Client::factory()->create();
    }

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);

        return $user;
    }

    public function test_creating_a_client_seeds_the_default_status_pipeline(): void
    {
        $client = $this->clientWithDefaults();

        $stages = $client->stages()->orderBy('position')->get();

        $this->assertCount(6, $stages);
        $this->assertEquals(
            ['Open', 'Under Review', 'Planned', 'In Progress', 'Complete', 'Closed'],
            $stages->pluck('name')->all(),
        );
        $this->assertEquals(1, $stages->where('type', Stage::TYPE_COMPLETE)->count());
        $this->assertEquals(3, $stages->where('show_on_roadmap', true)->count());
    }

    public function test_seeding_defaults_is_idempotent(): void
    {
        $client = $this->clientWithDefaults();

        Stage::seedDefaultsFor($client);

        $this->assertCount(6, $client->stages()->get());
    }

    public function test_setting_status_enum_backfills_stage_id(): void
    {
        $client = $this->clientWithDefaults();

        $post = Post::factory()->create([
            'client_id' => $client->id,
            'status' => Post::STATUS_PENDING,
        ]);

        $this->assertSame(Stage::TYPE_OPEN, $post->stage->type);

        $post->update(['status' => Post::STATUS_COMPLETED]);

        $this->assertSame(Stage::TYPE_COMPLETE, $post->fresh()->stage->type);
    }

    public function test_changing_stage_syncs_the_derived_status_mirror(): void
    {
        $client = $this->clientWithDefaults();
        $completeStage = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();

        $post = Post::factory()->create([
            'client_id' => $client->id,
            'status' => Post::STATUS_PENDING,
        ]);

        $post->update(['stage_id' => $completeStage->id]);

        $this->assertSame(Post::STATUS_COMPLETED, $post->fresh()->status);
    }

    public function test_moving_post_to_complete_stage_notifies_voters_and_fires_event(): void
    {
        Notification::fake();
        Event::fake([PostCompletedEvent::class]);

        $client = $this->clientWithDefaults();
        $completeStage = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();

        $author = Contact::factory()->create(['client_id' => $client->id]);
        $voter = Contact::factory()->create(['client_id' => $client->id]);

        $post = Post::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $author->id,
            'status' => Post::STATUS_PENDING,
        ]);

        $post->votes()->create(['contact_id' => $voter->id]);

        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->patch("/posts/{$post->id}/status", ['stage_id' => $completeStage->id])
            ->assertRedirect("posts/{$post->id}");

        $this->assertSame(Post::STATUS_COMPLETED, $post->fresh()->status);

        Notification::assertSentTo($author, PostCompletedNotification::class);
        Notification::assertSentTo($voter, PostCompletedNotification::class);
        Event::assertDispatched(PostCompletedEvent::class);
    }

    public function test_status_update_rejects_a_stage_from_another_client(): void
    {
        $client = $this->clientWithDefaults();
        $otherClient = $this->clientWithDefaults();
        $foreignStage = $otherClient->stages()->first();

        $post = Post::factory()->create(['client_id' => $client->id]);
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->patch("/posts/{$post->id}/status", ['stage_id' => $foreignStage->id])
            ->assertSessionHasErrors('stage_id');
    }
}
