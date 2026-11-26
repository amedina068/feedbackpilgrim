<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiBoardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_creating_a_client_seeds_a_default_board(): void
    {
        $client = Client::factory()->create();

        $boards = $client->boards()->get();

        $this->assertCount(1, $boards);
        $this->assertSame(Board::DEFAULT_SLUG, $boards->first()->slug);
        $this->assertTrue($boards->first()->is_public);
    }

    public function test_new_post_is_assigned_the_default_board(): void
    {
        $client = Client::factory()->create();

        $post = Post::factory()->create(['client_id' => $client->id]);

        $this->assertSame($client->boards()->first()->id, $post->board_id);
    }

    public function test_new_post_goes_to_the_oldest_board_when_boards_share_the_first_position(): void
    {
        $client = Client::factory()->create();
        $default = $client->boards()->sole();
        Board::factory()->count(5)->create(['client_id' => $client->id, 'position' => $default->position]);

        $post = Post::factory()->create(['client_id' => $client->id]);

        $this->assertSame($default->id, $post->board_id);
    }

    public function test_admin_can_create_a_board(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->post('/boards', ['name' => 'Bugs', 'is_public' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('boards', [
            'client_id' => $client->id,
            'name' => 'Bugs',
            'slug' => 'bugs',
        ]);
    }

    public function test_boards_admin_page_renders(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        Board::factory()->create(['client_id' => $client->id, 'name' => 'Bug Reports']);

        $this->actingAs($user)
            ->get('/boards')
            ->assertOk()
            ->assertSee('Boards')
            ->assertSee('Bug Reports');
    }

    public function test_admin_can_toggle_a_board_public_flag(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $board = Board::factory()->create(['client_id' => $client->id, 'is_public' => true]);

        $this->actingAs($user)
            ->patch("/boards/{$board->id}", ['name' => $board->name, 'is_public' => '0'])
            ->assertRedirect();

        $this->assertFalse($board->fresh()->is_public);

        $this->actingAs($user)
            ->patch("/boards/{$board->id}", ['name' => $board->name, 'is_public' => '1'])
            ->assertRedirect();

        $this->assertTrue($board->fresh()->is_public);
    }

    public function test_deleting_a_board_reassigns_its_posts(): void
    {
        $client = Client::factory()->create();
        $default = $client->boards()->first();
        $bugs = Board::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'board_id' => $bugs->id]);
        $user = $this->actingAdmin($client);

        $this->actingAs($user)->delete("/boards/{$bugs->id}")->assertRedirect();

        $this->assertDatabaseMissing('boards', ['id' => $bugs->id]);
        $this->assertSame($default->id, $post->fresh()->board_id);
    }

    public function test_cannot_delete_the_last_board(): void
    {
        $client = Client::factory()->create();
        $default = $client->boards()->first();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->delete("/boards/{$default->id}")
            ->assertSessionHasErrors('board');

        $this->assertDatabaseHas('boards', ['id' => $default->id]);
    }

    public function test_cannot_touch_a_board_from_another_client(): void
    {
        $client = Client::factory()->create();
        $foreign = Board::factory()->create(['client_id' => Client::factory()->create()->id]);
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->patch("/boards/{$foreign->id}", ['name' => 'X'])
            ->assertNotFound();
    }

    public function test_admin_post_index_is_filtered_by_board(): void
    {
        $client = Client::factory()->create();
        $default = $client->boards()->first();
        $bugs = Board::factory()->create(['client_id' => $client->id]);
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $keep = Post::factory()->create(['client_id' => $client->id, 'board_id' => $bugs->id, 'contact_id' => $contact->id, 'archived_at' => null, 'status' => Post::STATUS_PENDING]);
        Post::factory()->create(['client_id' => $client->id, 'board_id' => $default->id, 'contact_id' => $contact->id, 'archived_at' => null, 'status' => Post::STATUS_PENDING]);

        $user = $this->actingAdmin($client);

        $response = $this->actingAs($user)->get("/posts?board={$bugs->id}")->assertOk();

        $ids = collect($response->viewData('posts')->items())->pluck('id');
        $this->assertTrue($ids->contains($keep->id));
        $this->assertCount(1, $ids);
    }

    public function test_kanban_only_shows_posts_for_the_active_board(): void
    {
        $client = Client::factory()->create();
        $default = $client->boards()->first();
        $bugs = Board::factory()->create(['client_id' => $client->id, 'name' => 'Bug Reports']);
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $onBugs = Post::factory()->create(['client_id' => $client->id, 'board_id' => $bugs->id, 'contact_id' => $contact->id, 'title' => 'Bug board post']);
        $onDefault = Post::factory()->create(['client_id' => $client->id, 'board_id' => $default->id, 'contact_id' => $contact->id, 'title' => 'Default board post']);

        $user = $this->actingAdmin($client);

        $response = $this->actingAs($user)->get("/board?board={$bugs->id}")->assertOk();

        $ids = collect($response->viewData('stages'))
            ->flatMap(fn ($stage) => $stage->posts->pluck('id'));

        $this->assertTrue($ids->contains($onBugs->id));
        $this->assertFalse($ids->contains($onDefault->id));
        $this->assertSame($bugs->id, $response->viewData('activeBoard')->id);
    }

    public function test_kanban_defaults_to_the_first_board_without_a_board_param(): void
    {
        $client = Client::factory()->create();
        $default = $client->boards()->first();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->get('/board')
            ->assertOk()
            ->assertViewHas('activeBoard', fn ($board) => $board->id === $default->id);
    }

    public function test_kanban_404s_for_a_board_from_another_client(): void
    {
        $client = Client::factory()->create();
        $foreign = Board::factory()->create(['client_id' => Client::factory()->create()->id]);
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->get("/board?board={$foreign->id}")
            ->assertNotFound();
    }

    public function test_for_board_scope_is_a_noop_without_a_board(): void
    {
        $client = Client::factory()->create();
        Post::factory()->count(3)->create(['client_id' => $client->id]);

        $this->assertSame(3, Post::query()->where('client_id', $client->id)->forBoard(null)->count());
    }
}
