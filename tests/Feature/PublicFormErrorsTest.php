<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PublicFormErrorsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $this->app['auth']->guard('contact')->setUser($this->contact);
    }

    public function test_a_new_post_without_content_shows_the_error_and_keeps_the_input(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'billing']);
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'export']);

        $html = $this->from($this->siteUrl('/posts/create'))
            ->followingRedirects()
            ->post($this->siteUrl('/posts'), [
                'title' => 'Dark mode',
                'content' => '',
                'tags' => [$tag->id],
            ])
            ->assertOk()
            ->assertSee('The content field is required.')
            ->assertSee('value="Dark mode"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="tags-'.$tag->id.'"[^>]*checked/', $html);
        $this->assertSame(1, preg_match_all('/name="tags\[\]"[^>]*checked/', $html));
    }

    public function test_the_new_post_form_groups_the_tags_and_names_the_action(): void
    {
        Tag::factory()->create(['client_id' => $this->client->id]);

        $html = $this->get($this->siteUrl('/posts/create'))
            ->assertOk()
            ->assertSee('<legend', false)
            ->assertSee('Create post')
            ->getContent();

        $this->assertMatchesRegularExpression('/<textarea[^>]*name="content"[^>]*required/s', $html);
    }

    public function test_an_empty_comment_shows_the_error(): void
    {
        $post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
            'published_at' => now(),
        ]);

        $errors = (new ViewErrorBag)->put('default', new MessageBag(['content' => ['The content field is required.']]));

        $this->withSession(['errors' => $errors])
            ->get($this->siteUrl('/posts/'.$post->id))
            ->assertOk()
            ->assertSee('The content field is required.');

        $this->from($this->siteUrl('/posts/'.$post->id))
            ->post($this->siteUrl('/comments'), [
                'model_type' => Post::class,
                'model_id' => $post->id,
                'content' => '',
            ])
            ->assertSessionHasErrors('content');
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }
}
