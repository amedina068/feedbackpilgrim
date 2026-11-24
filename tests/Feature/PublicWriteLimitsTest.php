<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicWriteLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_17_000300_add_unique_index_to_votes_table.php';

    private Client $client;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $author = Contact::factory()->create(['client_id' => $this->client->id]);
        $this->post = Post::factory()->create([
            'client_id' => $this->client->id,
            'contact_id' => $author->id,
            'published_at' => now(),
        ]);
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    private function contact(): Contact
    {
        return Contact::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_a_contact_votes_only_once_on_a_post(): void
    {
        $contact = $this->contact();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($contact, 'contact')
                ->post($this->siteUrl('/posts/'.$this->post->id.'/votes'))
                ->assertRedirect();
        }

        $this->assertSame(1, $this->post->votes()->where('contact_id', $contact->id)->count());
    }

    public function test_the_database_refuses_a_second_vote_of_the_same_contact(): void
    {
        $contact = $this->contact();
        $this->post->votes()->create(['contact_id' => $contact->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->post->votes()->create(['contact_id' => $contact->id]);
    }

    public function test_the_migration_removes_duplicate_votes_and_keeps_the_oldest(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->down();

        $contact = $this->contact();
        $other = $this->contact();
        $rows = [
            ['contact_id' => $contact->id, 'created_at' => now()->subDays(3)],
            ['contact_id' => $contact->id, 'created_at' => now()->subDays(2)],
            ['contact_id' => $contact->id, 'created_at' => now()->subDay()],
            ['contact_id' => $other->id, 'created_at' => now()],
        ];

        foreach ($rows as $row) {
            DB::table('votes')->insert($row + [
                'votable_type' => Post::class,
                'votable_id' => $this->post->id,
                'updated_at' => $row['created_at'],
            ]);
        }

        $oldestId = Vote::query()->where('contact_id', $contact->id)->min('id');

        $migration->up();

        $this->assertSame([$oldestId], Vote::query()->where('contact_id', $contact->id)->pluck('id')->all());
        $this->assertSame(1, Vote::query()->where('contact_id', $other->id)->count());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->post->votes()->create(['contact_id' => $other->id]);
    }

    public function test_a_contact_cannot_post_comments_without_limit(): void
    {
        $contact = $this->contact();
        $statuses = [];

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $statuses[] = $this->actingAs($contact, 'contact')
                ->post($this->siteUrl('/comments'), [
                    'model_type' => Post::class,
                    'model_id' => $this->post->id,
                    'content' => 'Comment '.$attempt,
                ])
                ->getStatusCode();
        }

        $this->assertContains(429, $statuses);
        $this->assertLessThanOrEqual(10, $this->post->comments()->count());
    }

    public function test_one_ip_address_cannot_vote_without_limit_through_many_contacts(): void
    {
        $statuses = [];

        for ($attempt = 0; $attempt < 65; $attempt++) {
            $statuses[] = $this->actingAs($this->contact(), 'contact')
                ->post($this->siteUrl('/posts/'.$this->post->id.'/votes'))
                ->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_public_post_testimonial_and_vote_routes_are_throttled(): void
    {
        $contact = $this->contact();

        foreach (['/posts', '/testimonials', '/votes', '/posts/'.$this->post->id.'/votes'] as $path) {
            $statuses = [];

            for ($attempt = 0; $attempt < 35; $attempt++) {
                $statuses[] = $this->actingAs($contact, 'contact')
                    ->post($this->siteUrl($path), [])
                    ->getStatusCode();
            }

            $this->assertContains(429, $statuses, $path.' has no throttle.');
        }
    }

    public function test_the_api_limiter_allows_a_widget_rate_and_not_more(): void
    {
        $limits = RateLimiter::limiter('api')(Request::create('/api/v1/init', 'POST'));
        $limits = is_array($limits) ? $limits : [$limits];

        foreach ($limits as $limit) {
            $this->assertLessThanOrEqual(120, $limit->maxAttempts);
            $this->assertSame(60, $limit->decaySeconds);
        }
    }
}
