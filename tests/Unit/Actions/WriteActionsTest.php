<?php

namespace Tests\Unit\Actions;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\UpdateContact;
use App\Actions\Posts\CreatePost;
use App\Actions\Posts\UpdatePost;
use App\Actions\ReleaseNotes\CreateReleaseNote;
use App\Actions\ReleaseNotes\UpdateReleaseNote;
use App\Actions\Testimonials\CreateTestimonial;
use App\Actions\Testimonials\UpdateTestimonial;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\ClientContext;
use App\Support\ContactUpsertResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WriteActionsTest extends TestCase
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

    public function test_create_post_persists_scoped_to_client_with_tags(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id]);

        $post = (new CreatePost)($this->context, [
            'title' => 'New Post',
            'content' => 'Body',
            'tags' => [$tag->id],
        ]);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame($this->client->id, $post->client_id);
        $this->assertTrue($post->tags->contains($tag));
    }

    public function test_create_post_ignores_supplied_client_id(): void
    {
        $other = Client::factory()->create();

        $post = (new CreatePost)($this->context, [
            'title' => 'Scoped',
            'content' => 'Body',
            'client_id' => $other->id,
        ]);

        $this->assertSame($this->client->id, $post->client_id);
    }

    public function test_create_post_validation_fails_without_title(): void
    {
        $this->expectException(ValidationException::class);

        (new CreatePost)($this->context, ['content' => 'Body']);
    }

    public function test_update_post_throws_for_other_client(): void
    {
        $foreign = Post::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        (new UpdatePost)($this->context, ['id' => $foreign->id, 'title' => 'x']);
    }

    public function test_create_release_note_sets_author_from_context_and_forces_draft(): void
    {
        $note = (new CreateReleaseNote)($this->context, [
            'title' => 'Launch',
            'content' => 'Notes',
        ]);

        $this->assertSame($this->context->userId, $note->user_id);
        $this->assertNull($note->published_at);
        $this->assertSame('launch-'.now()->format('Ymd'), $note->slug);
    }

    public function test_create_contact_returns_was_created_true_then_false(): void
    {
        $first = (new CreateContact)($this->context, [
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $this->assertInstanceOf(ContactUpsertResult::class, $first);
        $this->assertTrue($first->wasCreated);

        $second = (new CreateContact)($this->context, [
            'name' => 'Jane Updated',
            'email' => 'jane@example.com',
        ]);

        $this->assertFalse($second->wasCreated);
        $this->assertSame($first->contact->id, $second->contact->id);
    }

    public function test_create_contact_stores_custom_fields_as_metadata(): void
    {
        $result = (new CreateContact)($this->context, [
            'name' => 'Meta',
            'email' => 'meta@example.com',
            'plan' => 'enterprise',
        ]);

        $this->assertSame('enterprise', json_decode($result->contact->fresh()->metadata, true)['plan']);
    }

    public function test_update_contact_modifies_record(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Old']);

        $updated = (new UpdateContact)($this->context, ['id' => $contact->id, 'name' => 'New']);

        $this->assertSame('New', $updated['name']);
    }

    public function test_update_release_note_changes_title_and_stays_scoped(): void
    {
        $note = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'title' => 'Old']);

        $updated = (new UpdateReleaseNote)($this->context, ['id' => $note->id, 'title' => 'New']);

        $this->assertSame('New', $updated->title);
    }

    public function test_update_testimonial_sets_approved_at(): void
    {
        $testimonial = Testimonial::factory()->create(['client_id' => $this->client->id, 'approved_at' => null]);

        $updated = (new UpdateTestimonial)($this->context, ['id' => $testimonial->id, 'approved_at' => '2026-06-16 00:00:00']);

        $this->assertNotNull($updated->approved_at);
    }

    public function test_create_testimonial_persists_scoped(): void
    {
        $t = (new CreateTestimonial)($this->context, [
            'description' => 'Great product',
            'username' => 'happyuser',
            'rating' => 5,
        ]);

        $this->assertSame($this->client->id, $t->client_id);
        $this->assertSame('happyuser', $t->username);
    }
}
