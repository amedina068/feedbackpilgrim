<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicPostSortTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Post $older;

    private Post $newer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->client = Client::factory()->create();
        $this->older = $this->createPost('Older idea', now()->subDay());
        $this->newer = $this->createPost('Newer idea', now());

        Vote::query()->create([
            'contact_id' => Contact::factory()->create(['client_id' => $this->client->id])->id,
            'votable_type' => Post::class,
            'votable_id' => $this->older->id,
        ]);
    }

    public function test_each_listed_sort_orders_the_posts(): void
    {
        $this->assertSortedIds('created_at__DESC', 'Newest', [$this->newer->id, $this->older->id]);
        $this->assertSortedIds('created_at__ASC', 'Oldest', [$this->older->id, $this->newer->id]);
        $this->assertSortedIds('votes_count__DESC', 'Most Voted', [$this->older->id, $this->newer->id]);
        $this->assertSortedIds('votes_count__ASC', 'Least Voted', [$this->newer->id, $this->older->id]);
    }

    public function test_an_unknown_sort_shows_the_newest_posts(): void
    {
        $this->assertSortedIds('title__DESC', 'Newest', [$this->newer->id, $this->older->id]);
        $this->assertSortedIds('id', 'Newest', [$this->newer->id, $this->older->id]);
        $this->assertSortedIds('created_at__DESC,(select 1)', 'Newest', [$this->newer->id, $this->older->id]);

        $this->get($this->siteUrl('/posts?sort_by[]=votes_count__ASC'))
            ->assertOk()
            ->assertViewHas('showingPostsFilter', 'Newest');
    }

    /**
     * @param  array<int, int>  $expectedIds
     */
    private function assertSortedIds(string $sortBy, string $label, array $expectedIds): void
    {
        $response = $this->get($this->siteUrl('/posts?sort_by='.urlencode($sortBy)))
            ->assertOk()
            ->assertViewHas('showingPostsFilter', $label);

        $this->assertSame($expectedIds, collect($response->viewData('posts')->items())->pluck('id')->all());
    }

    private function createPost(string $title, $createdAt): Post
    {
        return Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => Contact::factory()->create(['client_id' => $this->client->id])->id,
            'title' => $title,
            'status' => Post::STATUS_PENDING,
            'published_at' => now(),
            'archived_at' => null,
            'created_at' => $createdAt,
        ]);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }
}
