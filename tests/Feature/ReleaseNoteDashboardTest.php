<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNoteDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
    }

    public function test_the_create_form_has_no_status_field(): void
    {
        $this->actingAs($this->user)
            ->get('/release-notes/create')
            ->assertOk()
            ->assertSee('name="published_at"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_a_note_without_a_date_is_a_draft(): void
    {
        $this->actingAs($this->user)
            ->post('/release-notes', ['title' => 'Draft note', 'content' => 'Body'])
            ->assertRedirect();

        $this->assertNull(ReleaseNote::firstOrFail()->published_at);
    }

    public function test_a_note_with_a_date_is_published_on_that_date(): void
    {
        $this->actingAs($this->user)
            ->post('/release-notes', ['title' => 'Launch', 'content' => 'Body', 'published_at' => '2026-11-01'])
            ->assertRedirect();

        $this->assertSame('2026-11-01', ReleaseNote::firstOrFail()->published_at->toDateString());
    }

    public function test_the_edit_link_opens_the_edit_page(): void
    {
        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'title' => 'Version 3', 'content' => 'Old body']);

        $this->actingAs($this->user)
            ->get("/release-notes/{$releaseNote->id}")
            ->assertSee("/release-notes/{$releaseNote->id}/edit", false);

        $this->actingAs($this->user)
            ->get("/release-notes/{$releaseNote->id}/edit")
            ->assertOk()
            ->assertSee('Version 3')
            ->assertSee('Old body');
    }

    public function test_an_admin_updates_the_title_and_the_content(): void
    {
        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id]);

        $this->actingAs($this->user)
            ->patch("/release-notes/{$releaseNote->id}", ['title' => 'Version 3.1', 'content' => 'New body'])
            ->assertRedirect("/release-notes/{$releaseNote->id}")
            ->assertSessionHas('success', 'Release note updated');

        $releaseNote->refresh();
        $this->assertSame('Version 3.1', $releaseNote->title);
        $this->assertSame('New body', $releaseNote->content);
    }

    public function test_an_empty_title_is_refused(): void
    {
        $releaseNote = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'title' => 'Keep me']);

        $this->actingAs($this->user)
            ->from("/release-notes/{$releaseNote->id}/edit")
            ->patch("/release-notes/{$releaseNote->id}", ['title' => '', 'content' => 'Body'])
            ->assertRedirect("/release-notes/{$releaseNote->id}/edit")
            ->assertSessionHasErrors('title');

        $this->assertSame('Keep me', $releaseNote->fresh()->title);
    }

    public function test_a_release_note_of_another_client_returns_404(): void
    {
        $other = ReleaseNote::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'Not yours']);

        $this->actingAs($this->user)->get("/release-notes/{$other->id}/edit")->assertNotFound();
        $this->actingAs($this->user)->patch("/release-notes/{$other->id}", ['title' => 'Changed', 'content' => 'x'])->assertNotFound();

        $this->assertSame('Not yours', $other->fresh()->title);
    }
}
