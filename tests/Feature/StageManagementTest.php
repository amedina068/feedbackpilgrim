<?php

namespace Tests\Feature;

use App\Events\PostCompletedEvent;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);

        return $user;
    }

    public function test_board_page_renders(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);
        Post::factory()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->get('/board')
            ->assertOk()
            ->assertSee('Board')
            ->assertSee('Manage statuses');
    }

    public function test_admin_can_create_a_status(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->post('/stages', [
                'name' => 'Needs Triage',
                'color' => '#ff8800',
                'type' => Stage::TYPE_OPEN,
                'show_on_roadmap' => '1',
            ])
            ->assertRedirect();

        $stage = Stage::query()->where('client_id', $client->id)->where('name', 'Needs Triage')->first();

        $this->assertNotNull($stage);
        $this->assertSame('#ff8800', $stage->color);
        $this->assertTrue($stage->show_on_roadmap);
        $this->assertSame(7, $stage->position);
    }

    public function test_create_rejects_invalid_color_and_type(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->post('/stages', ['name' => 'X', 'color' => 'red', 'type' => 'bogus'])
            ->assertSessionHasErrors(['color', 'type']);
    }

    public function test_admin_can_update_a_status(): void
    {
        $client = Client::factory()->create();
        $stage = $client->stages()->ofType(Stage::TYPE_OPEN)->first();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->patch("/stages/{$stage->id}", [
                'name' => 'Renamed',
                'color' => '#123456',
                'type' => Stage::TYPE_PLANNED,
                'show_on_roadmap' => '1',
            ])
            ->assertRedirect();

        $stage->refresh();
        $this->assertSame('Renamed', $stage->name);
        $this->assertSame(Stage::TYPE_PLANNED, $stage->type);
    }

    public function test_cannot_update_a_stage_from_another_client(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $foreign = $other->stages()->first();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->patch("/stages/{$foreign->id}", [
                'name' => 'Hacked',
                'color' => '#000000',
                'type' => Stage::TYPE_OPEN,
            ])
            ->assertNotFound();
    }

    public function test_deleting_a_status_reassigns_its_posts_to_an_open_stage(): void
    {
        $client = Client::factory()->create();
        $planned = $client->stages()->ofType(Stage::TYPE_PLANNED)->first();
        $openStage = $client->stages()->ofType(Stage::TYPE_OPEN)->orderBy('position')->first();
        $user = $this->actingAdmin($client);

        $post = Post::factory()->create(['client_id' => $client->id, 'stage_id' => $planned->id]);

        $this->actingAs($user)->delete("/stages/{$planned->id}")->assertRedirect();

        $this->assertDatabaseMissing('stages', ['id' => $planned->id]);
        $this->assertSame($openStage->id, $post->fresh()->stage_id);
    }

    public function test_cannot_delete_the_only_complete_status(): void
    {
        $client = Client::factory()->create();
        $complete = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->delete("/stages/{$complete->id}")
            ->assertSessionHasErrors('stage');

        $this->assertDatabaseHas('stages', ['id' => $complete->id]);
    }

    public function test_admin_can_reorder_statuses(): void
    {
        $client = Client::factory()->create();
        $ids = $client->stages()->orderBy('position')->pluck('id')->reverse()->values()->all();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)->patch('/stages/reorder', ['stages' => $ids])->assertRedirect();

        foreach ($ids as $index => $id) {
            $this->assertSame($index + 1, Stage::query()->find($id)->position);
        }
    }

    public function test_board_move_endpoint_updates_stage_position_and_completes(): void
    {
        Event::fake([PostCompletedEvent::class]);

        $client = Client::factory()->create();
        $complete = $client->stages()->ofType(Stage::TYPE_COMPLETE)->first();
        $user = $this->actingAdmin($client);

        $post = Post::factory()->create(['client_id' => $client->id, 'status' => Post::STATUS_PENDING]);

        $this->actingAs($user)
            ->patchJson("/posts/{$post->id}/stage", ['stage_id' => $complete->id, 'position' => 2])
            ->assertOk()
            ->assertJsonStructure(['data']);

        $post->refresh();
        $this->assertSame($complete->id, $post->stage_id);
        $this->assertSame(2, $post->stage_position);
        $this->assertSame(Post::STATUS_COMPLETED, $post->status);
        Event::assertDispatched(PostCompletedEvent::class);
    }
}
