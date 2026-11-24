<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNotePublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ReleaseNote $releaseNote;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $this->user->id]);

        $this->releaseNote = ReleaseNote::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'published_at' => null,
        ]);
    }

    public function test_publish_sets_the_publication_date(): void
    {
        $this->freezeSecond();

        $this->actingAs($this->user)
            ->post("/release-notes/{$this->releaseNote->id}/publication")
            ->assertRedirect("release-notes/{$this->releaseNote->id}")
            ->assertSessionHas('success', 'Release note published');

        $this->assertTrue($this->releaseNote->fresh()->published_at->equalTo(now()));
    }

    public function test_publish_keeps_a_publication_date_that_exists(): void
    {
        $this->releaseNote->update(['published_at' => '2026-01-15 10:00:00']);

        $this->actingAs($this->user)->post("/release-notes/{$this->releaseNote->id}/publication");

        $this->assertSame('2026-01-15 10:00:00', $this->releaseNote->fresh()->published_at->toDateTimeString());
    }

    public function test_unpublish_clears_the_publication_date(): void
    {
        $this->releaseNote->update(['published_at' => now()]);

        $this->actingAs($this->user)
            ->delete("/release-notes/{$this->releaseNote->id}/publication")
            ->assertRedirect("release-notes/{$this->releaseNote->id}")
            ->assertSessionHas('success', 'Release note unpublished');

        $this->assertNull($this->releaseNote->fresh()->published_at);
    }

    public function test_the_page_shows_the_button_that_matches_the_publication_date(): void
    {
        $this->actingAs($this->user)
            ->get("/release-notes/{$this->releaseNote->id}")
            ->assertSee('Publish')
            ->assertDontSee('Unpublish');

        $this->releaseNote->update(['published_at' => now()]);

        $this->actingAs($this->user)
            ->get("/release-notes/{$this->releaseNote->id}")
            ->assertSee('Unpublish');
    }

    public function test_publish_returns_404_for_a_release_note_of_another_client(): void
    {
        $other = ReleaseNote::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'user_id' => $this->user->id,
            'published_at' => null,
        ]);

        $this->actingAs($this->user)
            ->post("/release-notes/{$other->id}/publication")
            ->assertNotFound();

        $this->assertNull($other->fresh()->published_at);
    }
}
