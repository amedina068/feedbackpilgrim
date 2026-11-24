<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardListMarkupTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
    }

    public function test_the_contact_page_lists_the_details_and_the_empty_states(): void
    {
        $contact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'ltv' => 1200,
            'notes' => '',
        ]);

        $this->actingAs($this->user)
            ->get("/contacts/{$contact->id}")
            ->assertOk()
            ->assertSeeInOrder(['<dl', '<dt', 'Name', '</dt>', '<dd', 'Ada Lovelace', '</dd>', 'Email address', 'ada@example.com', 'LTV', '$1,200.00', '</dl>'], false)
            ->assertSeeInOrder(['No Notes', 'href="/contacts/'.$contact->id.'/notes"', 'Add notes'], false)
            ->assertSeeInOrder(['No Posts', 'href="/posts/create?contact_id='.$contact->id.'"', 'Add posts'], false);
    }

    public function test_the_contact_list_links_each_row_to_the_contact(): void
    {
        $contact = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Ada Lovelace']);

        $this->actingAs($this->user)
            ->get('/contacts')
            ->assertOk()
            ->assertSee('href="/contacts/'.$contact->id.'"', false)
            ->assertSeeInOrder(['Ada Lovelace', '<dt class="sr-only">Comments</dt>', '<dt class="sr-only">Votes</dt>', '<dt class="sr-only">Posts</dt>'], false);
    }

    public function test_the_tag_page_lists_its_contacts(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $contact = Contact::factory()->create(['client_id' => $this->client->id, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $contact->tags()->attach($tag);

        $this->actingAs($this->user)
            ->get("/tags/{$tag->id}")
            ->assertOk()
            ->assertSeeInOrder(['href="/contacts/'.$contact->id.'"', 'Ada Lovelace', 'href="mailto:ada@example.com"', 'Posts created'], false)
            ->assertSee('No Posts');
    }
}
