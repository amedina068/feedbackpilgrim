<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use App\Services\WeeklyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard and the weekly report rank the most active contacts. The tests
 * run on SQLite, so they also prove that the ranking queries work there.
 */
class ContactRankingTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 12:00:00');

        $this->client = Client::factory()->create();

        $alice = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Alice']);
        $bob = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Bob']);
        Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Carol']);

        $posts = Post::factory()->count(2)->create([
            'client_id' => $this->client->id,
            'contact_id' => $alice->id,
            'published_at' => now(),
        ])->push(Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $bob->id,
            'published_at' => now(),
        ]));

        Comment::factory()->create([
            'contact_id' => $alice->id,
            'commentable_type' => Post::class,
            'commentable_id' => $posts->first()->id,
        ]);

        $posts->each(fn (Post $post) => $post->votes()->create(['contact_id' => $bob->id]));

        $otherClient = Client::factory()->create();
        Post::factory()->count(5)->create([
            'client_id' => $otherClient->id,
            'contact_id' => Contact::factory()->create(['client_id' => $otherClient->id])->id,
            'published_at' => now(),
        ]);
    }

    public function test_the_dashboard_ranks_the_contacts_by_published_posts_and_by_activity(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_COMPLETED]);
        $user->clients()->attach($this->client);
        Contact::factory()->create(['user_id' => $user->id, 'client_id' => $this->client->id, 'email' => $user->email]);

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $this->client->id])
            ->get('/dashboard')
            ->assertOk();

        $byPosts = $response->viewData('topContactsByPosts');
        $this->assertSame(['Alice', 'Bob'], $byPosts->pluck('name')->all());
        $this->assertSame([2, 1], $byPosts->pluck('published_posts_count')->all());

        $byActivity = $response->viewData('topContactsByActivity');
        $this->assertSame(['Bob', 'Alice'], $byActivity->pluck('name')->all());
        $this->assertSame([4, 3], $byActivity->pluck('total_activity')->all());
    }

    public function test_the_weekly_report_ranks_the_contacts_by_posts_and_by_comments(): void
    {
        $metrics = app(WeeklyReportService::class)
            ->generateReportForClient($this->client, now()->startOfWeek())
            ->metrics;

        $this->assertSame(['Alice', 'Bob'], array_column($metrics['contacts_with_most_posts'], 'name'));
        $this->assertSame([2, 1], array_column($metrics['contacts_with_most_posts'], 'posts_count'));
        $this->assertSame(['Alice'], array_column($metrics['contacts_with_most_comments'], 'name'));
    }
}
