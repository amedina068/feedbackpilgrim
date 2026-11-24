<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TestimonialCommentTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Contact $contact;

    private Testimonial $testimonial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->contact = Contact::factory()->create(['client_id' => $this->client->id]);

        $this->testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => now(),
        ]);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    public function test_contact_can_comment_on_a_testimonial_and_read_it_back(): void
    {
        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => Testimonial::class,
                'model_id' => $this->testimonial->id,
                'content' => 'Same experience here',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'commentable_type' => Testimonial::class,
            'commentable_id' => $this->testimonial->id,
            'contact_id' => $this->contact->id,
        ]);

        Auth::shouldUse('web');

        $this->get($this->siteUrl('/testimonials/'.$this->testimonial->id))
            ->assertOk()
            ->assertSee('Same experience here');
    }

    public function test_testimonial_comments_exclude_comments_of_a_post_with_the_same_id(): void
    {
        Comment::factory()->create([
            'contact_id' => $this->contact->id,
            'commentable_type' => Testimonial::class,
            'commentable_id' => $this->testimonial->id,
        ]);
        Comment::factory()->create([
            'contact_id' => $this->contact->id,
            'commentable_type' => Post::class,
            'commentable_id' => $this->testimonial->id,
        ]);

        $this->assertSame(1, $this->testimonial->comments()->count());
        $this->assertTrue($this->testimonial->comments()->first()->commentable->is($this->testimonial));
    }
}
