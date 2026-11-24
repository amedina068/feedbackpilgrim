<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Contacts\GetContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Posts\GetPostTool;
use App\Mcp\Tools\Posts\ListPostsTool;
use App\Mcp\Tools\ReleaseNotes\GetReleaseNoteTool;
use App\Mcp\Tools\ReleaseNotes\ListReleaseNotesTool;
use App\Mcp\Tools\Tags\ListTagsTool;
use App\Mcp\Tools\Testimonials\GetTestimonialTool;
use App\Mcp\Tools\Testimonials\ListTestimonialsTool;
use App\Models\Board;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);

        $token = $this->user->createToken('mcp', ['*'])->accessToken;
        $token->client_id = $this->client->id;
        $token->save();

        $this->user->withAccessToken($token);
    }

    private function callTool(string $tool, array $args = [])
    {
        return PublicServer::actingAs($this->user)->tool($tool, $args);
    }

    public function test_list_posts_returns_client_scoped_records(): void
    {
        Post::factory()->create(['client_id' => $this->client->id, 'title' => 'Mine Alpha']);
        Post::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'Theirs Beta']);

        $this->callTool(ListPostsTool::class)
            ->assertOk()
            ->assertSee('Mine Alpha')
            ->assertDontSee('Theirs Beta');
    }

    public function test_get_post_returns_record(): void
    {
        $post = Post::factory()->create(['client_id' => $this->client->id, 'title' => 'Detail Post']);

        $this->callTool(GetPostTool::class, ['id' => $post->id])
            ->assertOk()
            ->assertSee('Detail Post');
    }

    public function test_get_post_for_other_client_is_not_found_error(): void
    {
        $foreign = Post::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(GetPostTool::class, ['id' => $foreign->id])
            ->assertHasErrors();
    }

    public function test_get_contact_flattens_metadata(): void
    {
        $contact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'metadata' => json_encode(['plan' => 'enterprise']),
        ]);

        $this->callTool(GetContactTool::class, ['id' => $contact->id])
            ->assertOk()
            ->assertSee('enterprise');
    }

    public function test_list_contacts_is_scoped(): void
    {
        Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Scoped Person']);
        Contact::factory()->create(['client_id' => Client::factory()->create()->id, 'name' => 'Foreign Person']);

        $this->callTool(ListContactsTool::class)
            ->assertOk()
            ->assertSee('Scoped Person')
            ->assertDontSee('Foreign Person');
    }

    public function test_get_release_note_returns_record_and_isolates_other_clients(): void
    {
        $note = ReleaseNote::factory()->create(['client_id' => $this->client->id, 'title' => 'Mine Note']);
        $foreign = ReleaseNote::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(GetReleaseNoteTool::class, ['id' => $note->id])
            ->assertOk()
            ->assertSee('Mine Note');

        $this->callTool(GetReleaseNoteTool::class, ['id' => $foreign->id])
            ->assertHasErrors();
    }

    public function test_get_testimonial_returns_record_and_isolates_other_clients(): void
    {
        $testimonial = Testimonial::factory()->create(['client_id' => $this->client->id, 'username' => 'mineuser']);
        $foreign = Testimonial::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(GetTestimonialTool::class, ['id' => $testimonial->id])
            ->assertOk()
            ->assertSee('mineuser');

        $this->callTool(GetTestimonialTool::class, ['id' => $foreign->id])
            ->assertHasErrors();
    }

    public function test_list_release_notes_is_scoped(): void
    {
        ReleaseNote::factory()->create(['client_id' => $this->client->id, 'title' => 'Note Mine']);
        ReleaseNote::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'Note Theirs']);

        $this->callTool(ListReleaseNotesTool::class)
            ->assertOk()
            ->assertSee('Note Mine')
            ->assertDontSee('Note Theirs');
    }

    public function test_list_testimonials_defaults_to_pending(): void
    {
        Testimonial::factory()->create(['client_id' => $this->client->id, 'username' => 'pendinguser', 'approved_at' => null]);
        Testimonial::factory()->create(['client_id' => $this->client->id, 'username' => 'approveduser', 'approved_at' => now()]);

        $this->callTool(ListTestimonialsTool::class)
            ->assertOk()
            ->assertSee('pendinguser')
            ->assertDontSee('approveduser');
    }

    public function test_list_tags_is_scoped(): void
    {
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'roadmap']);
        Tag::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'foreigntag']);

        $this->callTool(ListTagsTool::class)
            ->assertOk()
            ->assertSee('roadmap')
            ->assertDontSee('foreigntag');
    }

    public function test_list_posts_filters_by_board(): void
    {
        $board = Board::factory()->create(['client_id' => $this->client->id]);
        $otherBoard = Board::factory()->create(['client_id' => $this->client->id]);
        Post::factory()->create(['client_id' => $this->client->id, 'board_id' => $board->id, 'title' => 'On Board One']);
        Post::factory()->create(['client_id' => $this->client->id, 'board_id' => $otherBoard->id, 'title' => 'On Board Two']);

        $this->callTool(ListPostsTool::class, ['board_id' => $board->id])
            ->assertOk()
            ->assertSee('On Board One')
            ->assertDontSee('On Board Two');
    }

    public function test_list_posts_honors_page_argument(): void
    {
        Post::factory()->create(['client_id' => $this->client->id, 'title' => 'Page One Post', 'created_at' => now()->subDay()]);
        Post::factory()->create(['client_id' => $this->client->id, 'title' => 'Page Two Post', 'created_at' => now()->subDays(2)]);

        $this->callTool(ListPostsTool::class, ['per_page' => 1, 'page' => 2, 'sort_by' => 'created_at', 'sort_direction' => 'desc'])
            ->assertOk()
            ->assertSee('"page":2')
            ->assertSee('Page Two Post')
            ->assertDontSee('Page One Post');
    }

    #[DataProvider('listToolProvider')]
    public function test_empty_list_is_success_not_error(string $tool): void
    {
        $this->callTool($tool)
            ->assertOk()
            ->assertHasNoErrors()
            ->assertSee('"total":0');
    }

    public static function listToolProvider(): array
    {
        return [
            'posts' => [ListPostsTool::class],
            'release_notes' => [ListReleaseNotesTool::class],
            'contacts' => [ListContactsTool::class],
            'testimonials' => [ListTestimonialsTool::class],
            'tags' => [ListTagsTool::class],
        ];
    }
}
