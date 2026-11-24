<?php

namespace Tests\Unit\Actions;

use App\Actions\Tags\CreateTag;
use App\Actions\Tags\DeleteTag;
use App\Actions\Tags\UpdateTag;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\ClientContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TagActionsTest extends TestCase
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

    public function test_create_tag_scoped_to_client(): void
    {
        $tag = (new CreateTag)($this->context, ['title' => 'roadmap']);

        $this->assertSame($this->client->id, $tag->client_id);
        $this->assertSame('roadmap', $tag->title);
    }

    public function test_create_tag_rejects_duplicate_title_per_client(): void
    {
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'dupe']);

        $this->expectException(ValidationException::class);

        (new CreateTag)($this->context, ['title' => 'dupe']);
    }

    public function test_same_title_allowed_for_different_client(): void
    {
        Tag::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'shared']);

        $tag = (new CreateTag)($this->context, ['title' => 'shared']);

        $this->assertSame($this->client->id, $tag->client_id);
    }

    public function test_update_tag_ignores_self_for_uniqueness(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'keep']);

        $updated = (new UpdateTag)($this->context, ['id' => $tag->id, 'title' => 'keep']);

        $this->assertSame('keep', $updated->title);
    }

    public function test_update_tag_for_other_client_throws(): void
    {
        $foreign = Tag::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        (new UpdateTag)($this->context, ['id' => $foreign->id, 'title' => 'x']);
    }

    public function test_delete_tag_detaches_relations_and_removes_it(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);
        $post->tags()->attach($tag);
        $contact->tags()->attach($tag);

        (new DeleteTag)($this->context, ['id' => $tag->id]);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertSame(0, $post->tags()->count());
        $this->assertSame(0, $contact->tags()->count());
    }

    public function test_delete_tag_for_other_client_throws(): void
    {
        $foreign = Tag::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        (new DeleteTag)($this->context, ['id' => $foreign->id]);
    }
}
