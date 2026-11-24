<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostScoreTest extends TestCase
{
    use RefreshDatabase;

    private function voteFor(Post $post, Client $client, ?float $mrr): void
    {
        $contact = Contact::factory()->create(['client_id' => $client->id, 'mrr' => $mrr]);
        $post->votes()->create(['contact_id' => $contact->id]);
    }

    public function test_score_is_vote_count_plus_summed_voter_mrr(): void
    {
        $client = Client::factory()->create();
        $post = Post::factory()->create(['client_id' => $client->id]);

        $this->voteFor($post, $client, 0.0);
        $this->voteFor($post, $client, 50.0);

        $scored = Post::query()->withScore()->find($post->id);

        // 2 votes + (0 + 50) MRR = 52
        $this->assertEquals(52, (int) round($scored->score));
    }

    public function test_score_is_zero_without_votes(): void
    {
        $client = Client::factory()->create();
        $post = Post::factory()->create(['client_id' => $client->id]);

        $scored = Post::query()->withScore()->find($post->id);

        $this->assertEquals(0, (int) round($scored->score));
    }

    public function test_high_mrr_post_outranks_a_more_voted_low_mrr_post(): void
    {
        $client = Client::factory()->create();

        $whale = Post::factory()->create(['client_id' => $client->id]);
        $this->voteFor($whale, $client, 500.0);

        $popular = Post::factory()->create(['client_id' => $client->id]);
        $this->voteFor($popular, $client, 0.0);
        $this->voteFor($popular, $client, 0.0);
        $this->voteFor($popular, $client, 0.0);

        $ranked = Post::query()
            ->where('client_id', $client->id)
            ->withScore()
            ->orderByDesc('score')
            ->get();

        $this->assertSame($whale->id, $ranked->first()->id);
    }
}
