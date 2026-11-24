<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use App\Support\PostMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoteAndMergeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_admin_can_add_a_vote_on_behalf_of_a_contact(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $post = Post::factory()->create(['client_id' => $client->id]);
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/votes", ['contact_id' => $contact->id])
            ->assertRedirect();

        $this->assertSame(1, $post->votes()->where('contact_id', $contact->id)->count());
    }

    public function test_duplicate_vote_is_rejected(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $post = Post::factory()->create(['client_id' => $client->id]);
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post->votes()->create(['contact_id' => $contact->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/votes", ['contact_id' => $contact->id])
            ->assertSessionHasErrors('contact_id');

        $this->assertSame(1, $post->votes()->where('contact_id', $contact->id)->count());
    }

    public function test_cannot_vote_with_a_contact_from_another_client(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $post = Post::factory()->create(['client_id' => $client->id]);
        $foreign = Contact::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/votes", ['contact_id' => $foreign->id])
            ->assertSessionHasErrors('contact_id');
    }

    public function test_votes_page_renders(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $post = Post::factory()->create(['client_id' => $client->id, 'title' => 'Votable']);

        $this->actingAs($user)
            ->get("/posts/{$post->id}/votes")
            ->assertOk()
            ->assertSee('Votable');
    }

    public function test_merge_moves_votes_and_comments_then_deletes_source(): void
    {
        $client = Client::factory()->create();
        $source = Post::factory()->create(['client_id' => $client->id]);
        $target = Post::factory()->create(['client_id' => $client->id]);

        $voterA = Contact::factory()->create(['client_id' => $client->id]);
        $voterB = Contact::factory()->create(['client_id' => $client->id]);
        $source->votes()->create(['contact_id' => $voterA->id]);
        $source->votes()->create(['contact_id' => $voterB->id]);
        $target->votes()->create(['contact_id' => $voterB->id]); // already voted target
        $source->comments()->create(['contact_id' => $voterA->id, 'content' => 'moved comment']);

        PostMerger::merge($source, $target);

        $this->assertDatabaseMissing('posts', ['id' => $source->id]);
        // voterB's duplicate dropped, voterA moved over => 2 unique votes on target
        $this->assertSame(2, $target->votes()->count());
        $this->assertSame(1, $target->comments()->count());
    }

    public function test_merge_moves_attachments_to_target(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $source = Post::factory()->create(['client_id' => $client->id]);
        $target = Post::factory()->create(['client_id' => $client->id]);

        $source->addMedia(UploadedFile::fake()->image('mockup.png'))
            ->toMediaCollection('attachments');

        PostMerger::merge($source, $target);

        $this->assertCount(1, $target->fresh()->getMedia('attachments'));
        $this->assertDatabaseMissing('media', [
            'model_type' => Post::class,
            'model_id' => $source->id,
        ]);
    }

    public function test_admin_can_merge_via_endpoint(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $source = Post::factory()->create(['client_id' => $client->id]);
        $target = Post::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->post("/posts/{$source->id}/merge", ['target_post_id' => $target->id])
            ->assertRedirect("/posts/{$target->id}");

        $this->assertDatabaseMissing('posts', ['id' => $source->id]);
    }

    public function test_cannot_merge_into_a_post_from_another_client(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $source = Post::factory()->create(['client_id' => $client->id]);
        $foreign = Post::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($user)
            ->post("/posts/{$source->id}/merge", ['target_post_id' => $foreign->id])
            ->assertSessionHasErrors('target_post_id');

        $this->assertDatabaseHas('posts', ['id' => $source->id]);
    }
}
