<?php

namespace Tests\Unit\Actions;

use App\Actions\Contacts\GetContact;
use App\Actions\Contacts\ListContacts;
use App\Actions\Posts\GetPost;
use App\Actions\Posts\ListPosts;
use App\Actions\ReleaseNotes\ListReleaseNotes;
use App\Actions\Tags\ListTags;
use App\Actions\Testimonials\ListTestimonials;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\ClientContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ReadActionsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->client = Client::factory()->create();
        $user->clients()->attach($this->client);

        $token = $user->createToken('t', ['*'])->accessToken;
        $token->client_id = $this->client->id;
        $token->save();

        $this->context = ClientContext::fromToken($token);
    }

    public function test_list_posts_returns_paginator_scoped_to_client(): void
    {
        Post::factory()->count(3)->create(['client_id' => $this->client->id]);
        Post::factory()->count(2)->create(['client_id' => Client::factory()->create()->id]);

        $result = (new ListPosts)($this->context, []);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->total());
    }

    public function test_list_posts_clamps_invalid_sort_direction(): void
    {
        Post::factory()->count(2)->create(['client_id' => $this->client->id]);

        // Must not throw: invalid direction is clamped, never reaches orderBy raw.
        $result = (new ListPosts)($this->context, ['sort_by' => 'title', 'sort_direction' => 'sideways']);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(2, $result->total());
    }

    public function test_list_posts_clamps_per_page_to_max_100(): void
    {
        $result = (new ListPosts)($this->context, ['per_page' => 5000]);

        $this->assertSame(100, $result->perPage());
    }

    public function test_get_post_returns_model_scoped_to_client(): void
    {
        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $result = (new GetPost)($this->context, ['id' => $post->id]);

        $this->assertInstanceOf(Post::class, $result);
        $this->assertSame($post->id, $result->id);
    }

    public function test_get_post_throws_for_other_client(): void
    {
        $foreign = Post::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        (new GetPost)($this->context, ['id' => $foreign->id]);
    }

    public function test_list_contacts_returns_presented_records_without_raw_metadata(): void
    {
        Contact::factory()->create([
            'client_id' => $this->client->id,
            'metadata' => json_encode(['plan' => 'enterprise']),
        ]);

        $result = (new ListContacts)($this->context, []);
        $first = $result->getCollection()->first();

        $this->assertIsArray($first);
        $this->assertArrayNotHasKey('metadata', $first);
        $this->assertSame('enterprise', $first['plan']);
    }

    public function test_get_contact_returns_presented_array(): void
    {
        $contact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'metadata' => json_encode(['tier' => 'gold']),
        ]);

        $result = (new GetContact)($this->context, ['id' => $contact->id]);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('metadata', $result);
        $this->assertSame('gold', $result['tier']);
    }

    public function test_list_release_notes_published_filter(): void
    {
        ReleaseNote::factory()->create(['client_id' => $this->client->id, 'published_at' => now()]);
        ReleaseNote::factory()->create(['client_id' => $this->client->id, 'published_at' => null]);

        $published = (new ListReleaseNotes)($this->context, ['published' => true]);
        $this->assertSame(1, $published->total());

        $drafts = (new ListReleaseNotes)($this->context, ['published' => false]);
        $this->assertSame(1, $drafts->total());
    }

    public function test_list_testimonials_approved_branch(): void
    {
        Testimonial::factory()->create(['client_id' => $this->client->id, 'approved_at' => now()]);
        Testimonial::factory()->create(['client_id' => $this->client->id, 'approved_at' => null]);

        $approved = (new ListTestimonials)($this->context, ['approved' => true]);
        $this->assertSame(1, $approved->total());

        // Omitting approved returns pending only (documented parity behavior).
        $pending = (new ListTestimonials)($this->context, []);
        $this->assertSame(1, $pending->total());
    }

    public function test_list_tags_defaults_to_ascending_and_is_scoped(): void
    {
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'zeta']);
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'alpha']);

        $result = (new ListTags)($this->context, []);

        $titles = $result->getCollection()->pluck('title')->all();
        $this->assertSame(['alpha', 'zeta'], $titles);
    }
}
