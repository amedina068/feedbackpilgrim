<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftReleaseNoteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ReleaseNote $draft;

    private ReleaseNote $published;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($this->client);

        $this->draft = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $user->id,
            'title' => 'Unannounced feature',
            'published_at' => null,
        ]);

        $this->published = ReleaseNote::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $user->id,
            'title' => 'Shipped feature',
            'published_at' => now(),
        ]);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    private function contact(): Contact
    {
        return Contact::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_draft_release_note_page_returns_404(): void
    {
        $this->get($this->siteUrl('/release-notes/'.$this->published->slug))
            ->assertOk()
            ->assertSee('Shipped feature');

        $this->get($this->siteUrl('/release-notes/'.$this->draft->slug))->assertNotFound();
    }

    public function test_contact_cannot_comment_on_a_draft_release_note(): void
    {
        $this->actingAs($this->contact(), 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => ReleaseNote::class,
                'model_id' => $this->draft->id,
                'content' => 'Hello',
            ])
            ->assertNotFound();

        $this->assertSame(0, $this->draft->comments()->count());
    }

    public function test_contact_cannot_vote_on_a_draft_release_note(): void
    {
        $this->actingAs($this->contact(), 'contact')
            ->post($this->siteUrl('/votes'), [
                'model_type' => ReleaseNote::class,
                'model_id' => $this->draft->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, $this->draft->votes()->count());
    }

    public function test_contact_can_comment_and_vote_on_a_published_release_note(): void
    {
        $contact = $this->contact();

        $this->actingAs($contact, 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => ReleaseNote::class,
                'model_id' => $this->published->id,
                'content' => 'Great',
            ])
            ->assertRedirect();

        $this->actingAs($contact, 'contact')
            ->post($this->siteUrl('/votes'), [
                'model_type' => ReleaseNote::class,
                'model_id' => $this->published->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->published->comments()->count());
        $this->assertSame(1, $this->published->votes()->count());
    }
}
