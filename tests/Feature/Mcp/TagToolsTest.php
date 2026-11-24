<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Tags\CreateTagTool;
use App\Mcp\Tools\Tags\DeleteTagTool;
use App\Mcp\Tools\Tags\UpdateTagTool;
use App\Models\Client;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagToolsTest extends TestCase
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

    public function test_create_tag_persists_scoped(): void
    {
        $this->callTool(CreateTagTool::class, ['title' => 'roadmap'])
            ->assertOk()
            ->assertSee('roadmap');

        $this->assertDatabaseHas('tags', ['title' => 'roadmap', 'client_id' => $this->client->id]);
    }

    public function test_create_tag_duplicate_is_error(): void
    {
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'dupe']);

        $this->callTool(CreateTagTool::class, ['title' => 'dupe'])
            ->assertHasErrors();
    }

    public function test_update_tag_renames(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'old']);

        $this->callTool(UpdateTagTool::class, ['id' => $tag->id, 'title' => 'new'])
            ->assertOk()
            ->assertSee('new');
    }

    public function test_delete_tag_removes_it(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id]);

        $this->callTool(DeleteTagTool::class, ['id' => $tag->id])
            ->assertOk()
            ->assertSee('"deleted":true');

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_update_tag_for_other_client_is_not_found(): void
    {
        $foreign = Tag::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'foreign']);

        $this->callTool(UpdateTagTool::class, ['id' => $foreign->id, 'title' => 'hijack'])
            ->assertHasErrors();

        $this->assertDatabaseHas('tags', ['id' => $foreign->id, 'title' => 'foreign']);
    }

    public function test_delete_tag_for_other_client_is_not_found(): void
    {
        $foreign = Tag::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->callTool(DeleteTagTool::class, ['id' => $foreign->id])
            ->assertHasErrors();

        $this->assertDatabaseHas('tags', ['id' => $foreign->id]);
    }
}
