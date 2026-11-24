<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Stage;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStatesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->contact = Contact::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_the_post_page_shows_the_status_of_the_post(): void
    {
        $post = $this->makePost(['status' => Post::STATUS_IN_PROGRESS]);

        $this->get($this->siteUrl('/posts/'.$post->id))
            ->assertOk()
            ->assertSee('In Progress');
    }

    public function test_the_home_page_shows_the_in_progress_status_and_a_link_to_all_posts(): void
    {
        $this->makePost(['status' => Post::STATUS_IN_PROGRESS]);

        $this->get($this->siteUrl('/'))
            ->assertOk()
            ->assertSee('In Progress')
            ->assertSee('View all posts');
    }

    public function test_the_home_page_has_an_empty_state(): void
    {
        $this->get($this->siteUrl('/'))
            ->assertOk()
            ->assertSee('No posts yet.');
    }

    public function test_the_author_sees_that_a_testimonial_waits_for_review(): void
    {
        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
            'title' => 'Great tool',
            'approved_at' => null,
        ]);
        $this->app['auth']->guard('contact')->setUser($this->contact);

        $this->get($this->siteUrl('/testimonials'))
            ->assertOk()
            ->assertSee('Pending review');

        $this->get($this->siteUrl('/testimonials/'.$testimonial->id))
            ->assertOk()
            ->assertSee('Pending review');
    }

    public function test_a_release_note_comment_shows_in_full(): void
    {
        $note = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'published_at' => now()]);
        $note->comments()->create(['contact_id' => $this->contact->id, 'content' => 'First line of a **long** comment']);

        $this->get($this->siteUrl('/release-notes/'.$note->slug))
            ->assertOk()
            ->assertSee('<strong>long</strong>', false)
            ->assertDontSee('line-clamp-2', false);
    }

    public function test_the_roadmap_says_when_it_has_no_stages(): void
    {
        Stage::query()->where('client_id', $this->client->id)->delete();

        $this->get($this->siteUrl('/roadmap'))
            ->assertOk()
            ->assertSee('Nothing on the roadmap yet.');

        $this->get($this->siteUrl('/iframe/roadmap'))
            ->assertOk()
            ->assertSee('Nothing on the roadmap yet.');
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::factory()->create(array_merge([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
            'published_at' => now(),
        ], $attributes));
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }
}
