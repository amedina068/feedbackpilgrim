<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public site, the widget and the account forms accept text from anyone.
 * Each text field has a size limit, and each account form has a rate limit.
 */
class PublicWriteInputTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        User::factory()->create()->clients()->attach($this->client);
        $this->contact = Contact::factory()->create(['client_id' => $this->client->id]);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    private function publicPost(): Post
    {
        return Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
            'published_at' => now(),
        ]);
    }

    public function test_a_public_post_title_has_at_most_255_characters(): void
    {
        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/posts'), ['title' => str_repeat('a', 256), 'content' => 'Body'])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_public_post_body_has_at_most_10000_characters(): void
    {
        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/posts'), ['title' => 'Idea', 'content' => str_repeat('a', 10001)])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_public_comment_has_at_most_5000_characters(): void
    {
        $post = $this->publicPost();

        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => Post::class,
                'model_id' => $post->id,
                'content' => str_repeat('a', 5001),
            ])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_a_public_testimonial_has_at_most_5000_characters(): void
    {
        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/testimonials'), ['title' => 'Great', 'description' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_a_widget_testimonial_has_at_most_5000_characters(): void
    {
        $this->postJson($this->siteUrl('/api/v1/testimonials'), ['description' => str_repeat('a', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        $this->assertDatabaseCount('testimonials', 0);
    }

    public function test_a_contact_cannot_comment_on_a_testimonial_that_is_not_approved(): void
    {
        $author = Contact::factory()->create(['client_id' => $this->client->id]);
        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $author->id,
            'approved_at' => null,
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/comments'), [
                'model_type' => Testimonial::class,
                'model_id' => $testimonial->id,
                'content' => 'Nice',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_a_contact_cannot_vote_on_a_testimonial_that_is_not_approved(): void
    {
        $author = Contact::factory()->create(['client_id' => $this->client->id]);
        $testimonial = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $author->id,
            'approved_at' => null,
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post($this->siteUrl('/votes'), [
                'model_type' => Testimonial::class,
                'model_id' => $testimonial->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('votes', 0);
    }

    public function test_the_forgot_password_form_has_a_rate_limit(): void
    {
        $this->completeInstanceSetup();

        foreach (range(1, 10) as $attempt) {
            $this->post('/forgot-password', ['email' => "person{$attempt}@example.com"])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'one-more@example.com'])->assertTooManyRequests();
    }

    public function test_the_registration_form_has_a_rate_limit(): void
    {
        $this->completeInstanceSetup();
        config(['app.registration_enabled' => true]);

        foreach (range(1, 10) as $attempt) {
            $this->post('/register', ['name' => 'X', 'email' => 'bad'])->assertStatus(302);
        }

        $this->post('/register', ['name' => 'X', 'email' => 'bad'])->assertTooManyRequests();
    }

    public function test_the_reset_password_form_has_a_rate_limit(): void
    {
        $this->completeInstanceSetup();

        foreach (range(1, 10) as $attempt) {
            $this->post('/reset-password', ['token' => 'x', 'email' => 'a@example.com'])->assertStatus(302);
        }

        $this->post('/reset-password', ['token' => 'x', 'email' => 'a@example.com'])->assertTooManyRequests();
    }

    public function test_the_confirm_password_form_has_a_rate_limit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($user)->post('/confirm-password', ['password' => 'wrong'])->assertStatus(302);
        }

        $this->actingAs($user)->post('/confirm-password', ['password' => 'wrong'])->assertTooManyRequests();
    }
}
