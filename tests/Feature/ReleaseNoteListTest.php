<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNoteListTest extends TestCase
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

    public function test_each_row_shows_the_vote_count_the_excerpt_and_the_state(): void
    {
        $published = $this->note('Version 2', ['content' => "## Dark mode\n\nThe app has a **dark** theme.", 'published_at' => now()]);
        $this->note('Version 3', ['published_at' => null]);
        $this->vote($published, 3);

        $this->actingAs($this->user)
            ->get('/release-notes')
            ->assertOk()
            ->assertSeeInOrder(['3', 'votes'])
            ->assertSee('The app has a dark theme.')
            ->assertDontSee('**dark**')
            ->assertSee('Published')
            ->assertSee('Draft');
    }

    public function test_the_list_sorts_by_votes(): void
    {
        $few = $this->note('Few votes');
        $many = $this->note('Many votes');
        $this->vote($few, 1);
        $this->vote($many, 4);

        $this->actingAs($this->user)
            ->get('/release-notes?sort_by=votes_count__DESC')
            ->assertOk()
            ->assertSeeInOrder(['Many votes', 'Few votes']);

        $this->actingAs($this->user)
            ->get('/release-notes?sort_by=votes_count__ASC')
            ->assertOk()
            ->assertSeeInOrder(['Few votes', 'Many votes']);
    }

    public function test_the_list_filters_by_tag(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $tagged = $this->note('Tagged note');
        $tagged->tags()->attach($tag);
        $this->note('Other note');

        $this->actingAs($this->user)
            ->get('/release-notes?tags[]='.$tag->id)
            ->assertOk()
            ->assertSee('Tagged note')
            ->assertDontSee('Other note');
    }

    public function test_an_unknown_sort_shows_the_newest_first(): void
    {
        $this->note('Old', ['created_at' => now()->subDay()]);
        $this->note('New');

        $this->actingAs($this->user)
            ->get('/release-notes?sort_by=title__ASC')
            ->assertOk()
            ->assertSeeInOrder(['New', 'Old']);
    }

    public function test_the_show_page_has_no_link_to_a_missing_page(): void
    {
        $note = $this->note('Version 2');

        $this->actingAs($this->user)
            ->get('/release-notes/'.$note->id)
            ->assertOk()
            ->assertDontSee('/notes"', false);
    }

    public function test_the_contact_page_has_no_link_to_a_missing_page(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        for ($i = 0; $i < 6; $i++) {
            $post = Post::factory()->create(['client_id' => $this->client->id]);
            $post->votes()->create(['contact_id' => $contact->id]);
            $post->comments()->create(['contact_id' => $contact->id, 'content' => 'Comment '.$i]);
        }

        $this->actingAs($this->user)
            ->get('/contacts/'.$contact->id)
            ->assertOk()
            ->assertSee('Showing the 5 latest of 6 comments.')
            ->assertSee('Showing the 5 latest of 6 votes.')
            ->assertDontSee('/contact/'.$contact->id.'/comments', false)
            ->assertDontSee('/contacts/'.$contact->id.'/votes', false);
    }

    private function note(string $title, array $attributes = []): ReleaseNote
    {
        return ReleaseNote::factory()->create(array_merge([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'title' => $title,
            'published_at' => now(),
        ], $attributes));
    }

    private function vote(ReleaseNote $note, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $note->votes()->create(['contact_id' => Contact::factory()->create(['client_id' => $this->client->id])->id]);
        }
    }
}
