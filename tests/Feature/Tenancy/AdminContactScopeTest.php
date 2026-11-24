<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user can be a member of many clients, and the user has one admin contact
 * in each client. Each dashboard and public-site action must use the admin
 * contact and the records of the current client only.
 */
class AdminContactScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $firstClient;

    private Client $secondClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['onboarding' => User::ONBOARDING_COMPLETED]);
        $this->firstClient = Client::factory()->create();
        $this->secondClient = Client::factory()->create();
        $this->user->clients()->attach([$this->firstClient->id, $this->secondClient->id]);

        // The first client gets the older admin contact.
        $this->user->adminContactFor($this->firstClient);
        $this->user->adminContactFor($this->secondClient);
    }

    private function adminContactOf(Client $client): Contact
    {
        return Contact::query()->where('user_id', $this->user->id)->where('client_id', $client->id)->sole();
    }

    private function inSecondClient(): static
    {
        return $this->actingAs($this->user)->withSession(['fp_client_id' => $this->secondClient->id]);
    }

    private function postOf(Client $client, array $attributes = []): Post
    {
        return Post::factory()->create(array_merge([
            'client_id' => $client->id,
            'contact_id' => Contact::factory()->create(['client_id' => $client->id])->id,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_the_public_site_signs_the_admin_in_as_the_contact_of_that_client(): void
    {
        $url = 'http://'.$this->secondClient->slug.'.'.config('app.domain').'/posts';

        $this->inSecondClient()->get($url)->assertOk();

        $this->assertAuthenticatedAs($this->adminContactOf($this->secondClient), 'contact');
    }

    public function test_a_dashboard_post_without_a_contact_uses_the_admin_contact_of_the_current_client(): void
    {
        $this->inSecondClient()->post('/posts', ['title' => 'From the team', 'content' => 'Body'])->assertRedirect();

        $post = Post::query()->where('title', 'From the team')->sole();

        $this->assertSame($this->secondClient->id, $post->client_id);
        $this->assertSame($this->adminContactOf($this->secondClient)->id, $post->contact_id);
    }

    public function test_a_dashboard_comment_uses_the_admin_contact_of_the_current_client(): void
    {
        $post = $this->postOf($this->secondClient);

        $this->inSecondClient()->post("/posts/{$post->id}/comments", ['content' => 'Thanks'])->assertRedirect();

        $this->assertSame($this->adminContactOf($this->secondClient)->id, $post->comments()->sole()->contact_id);
    }

    public function test_the_post_notes_page_changes_the_post_of_the_url(): void
    {
        $firstPost = $this->postOf($this->secondClient, ['notes' => 'first']);
        $secondPost = $this->postOf($this->secondClient, ['notes' => 'second']);

        $this->inSecondClient()->get("/posts/{$secondPost->id}/notes")->assertOk()->assertSee('second');

        $this->inSecondClient()->patch("/posts/{$secondPost->id}/notes", ['notes' => 'changed'])
            ->assertRedirect("posts/{$secondPost->id}");

        $this->assertSame('first', $firstPost->fresh()->notes);
        $this->assertSame('changed', $secondPost->fresh()->notes);
    }

    public function test_the_post_notes_page_refuses_a_post_of_another_client(): void
    {
        $otherPost = $this->postOf($this->firstClient, ['notes' => 'other']);

        $this->inSecondClient()->get("/posts/{$otherPost->id}/notes")->assertNotFound();
        $this->inSecondClient()->patch("/posts/{$otherPost->id}/notes", ['notes' => 'changed'])->assertNotFound();

        $this->assertSame('other', $otherPost->fresh()->notes);
    }

    public function test_the_contact_notes_page_changes_the_contact_of_the_url(): void
    {
        $firstContact = Contact::factory()->create(['client_id' => $this->secondClient->id, 'notes' => 'first']);
        $secondContact = Contact::factory()->create(['client_id' => $this->secondClient->id, 'notes' => 'second']);

        $this->inSecondClient()->get("/contacts/{$secondContact->id}/notes")->assertOk()->assertSee('second');

        $this->inSecondClient()->patch("/contacts/{$secondContact->id}/notes", ['notes' => 'changed'])
            ->assertRedirect("contacts/{$secondContact->id}");

        $this->assertSame('first', $firstContact->fresh()->notes);
        $this->assertSame('changed', $secondContact->fresh()->notes);
    }

    public function test_the_contact_notes_page_refuses_a_contact_of_another_client(): void
    {
        $otherContact = Contact::factory()->create(['client_id' => $this->firstClient->id, 'notes' => 'other']);

        $this->inSecondClient()->patch("/contacts/{$otherContact->id}/notes", ['notes' => 'changed'])->assertNotFound();

        $this->assertSame('other', $otherContact->fresh()->notes);
    }

    public function test_an_unknown_sort_value_on_the_dashboard_lists_shows_the_default_order(): void
    {
        foreach (['/posts', '/contacts'] as $path) {
            foreach (['nonsense', 'title__sideways', 'users.password__asc'] as $sortBy) {
                $this->inSecondClient()->get("{$path}?sort_by={$sortBy}")->assertOk();
            }
        }
    }
}
