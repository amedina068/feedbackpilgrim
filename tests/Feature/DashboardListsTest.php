<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardListsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
    }

    public function test_the_search_and_filter_forms_keep_the_board(): void
    {
        $board = Board::query()->where('client_id', $this->client->id)->firstOrFail();

        $html = $this->actingAs($this->user)
            ->get('/posts?board='.$board->id)
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, '<input type="hidden" name="board" value="'.$board->id.'">'));
    }

    public function test_the_testimonial_actions_are_not_inside_the_row_link(): void
    {
        Testimonial::factory()->create(['client_id' => $this->client->id, 'title' => 'Great tool']);

        $html = $this->actingAs($this->user)
            ->get('/testimonials')
            ->assertOk()
            ->assertSee('Approve')
            ->getContent();

        preg_match('/<a href="\/testimonials\/\d+"[^>]*>(.*?)<\/a>/s', $html, $row);

        $this->assertNotEmpty($row);
        $this->assertStringNotContainsString('<form', $row[1]);
    }

    public function test_the_edit_post_page_says_edit(): void
    {
        $post = Post::factory()->create(['client_id' => $this->client->id]);

        $this->actingAs($this->user)
            ->get('/posts/'.$post->id.'/edit')
            ->assertOk()
            ->assertSee('Edit post')
            ->assertDontSee('Create Post');
    }
}
