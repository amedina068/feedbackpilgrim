<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Client;
use App\Models\Post;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadmapTest extends TestCase
{
    use RefreshDatabase;

    private function roadmapUrl(Client $client, string $query = ''): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').'/roadmap'.$query;
    }

    public function test_roadmap_shows_published_posts_grouped_by_roadmap_stages(): void
    {
        $client = Client::factory()->create();
        $planned = $client->stages()->ofType(Stage::TYPE_PLANNED)->first();
        $open = $client->stages()->ofType(Stage::TYPE_OPEN)->orderBy('position')->first();

        $visible = Post::factory()->create([
            'client_id' => $client->id,
            'stage_id' => $planned->id,
            'title' => 'Dark mode please',
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $offRoadmap = Post::factory()->create([
            'client_id' => $client->id,
            'stage_id' => $open->id,
            'title' => 'Brand new idea',
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $unpublished = Post::factory()->create([
            'client_id' => $client->id,
            'stage_id' => $planned->id,
            'title' => 'Secret draft',
            'published_at' => null,
            'archived_at' => null,
        ]);

        $response = $this->get($this->roadmapUrl($client))->assertOk();

        $response->assertSee('Dark mode please');
        $response->assertDontSee('Brand new idea');
        $response->assertDontSee('Secret draft');
    }

    public function test_roadmap_only_lists_roadmap_stages_as_columns(): void
    {
        $client = Client::factory()->create();

        $response = $this->get($this->roadmapUrl($client))->assertOk();

        $stages = $response->viewData('stages');

        $this->assertTrue($stages->every(fn ($stage) => $stage->show_on_roadmap));
        $this->assertEqualsCanonicalizing(
            ['Planned', 'In Progress', 'Complete'],
            $stages->pluck('name')->all(),
        );
    }

    public function test_iframe_roadmap_renders_published_roadmap_posts(): void
    {
        $client = Client::factory()->create();
        $planned = $client->stages()->ofType(Stage::TYPE_PLANNED)->first();

        Post::factory()->create([
            'client_id' => $client->id,
            'stage_id' => $planned->id,
            'title' => 'Embedded roadmap item',
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $url = 'http://'.$client->slug.'.'.config('app.domain').'/iframe/roadmap';

        $this->get($url)
            ->assertOk()
            ->assertSee('Embedded roadmap item');
    }

    public function test_roadmap_can_be_filtered_by_board(): void
    {
        $client = Client::factory()->create();
        $planned = $client->stages()->ofType(Stage::TYPE_PLANNED)->first();
        $default = $client->boards()->first();
        $bugs = Board::factory()->create(['client_id' => $client->id]);

        Post::factory()->create([
            'client_id' => $client->id,
            'board_id' => $default->id,
            'stage_id' => $planned->id,
            'title' => 'Feature board item',
            'published_at' => now(),
            'archived_at' => null,
        ]);

        Post::factory()->create([
            'client_id' => $client->id,
            'board_id' => $bugs->id,
            'stage_id' => $planned->id,
            'title' => 'Bug board item',
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $response = $this->get($this->roadmapUrl($client, '?board='.$bugs->slug))->assertOk();

        $response->assertSee('Bug board item');
        $response->assertDontSee('Feature board item');
    }
}
