<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashMessageTest extends TestCase
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

    public function test_the_dashboard_shows_the_success_message_of_the_last_action(): void
    {
        $this->actingAs($this->user)
            ->withSession(['success' => 'Tag renamed'])
            ->get('/tags')
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('Tag renamed');
    }

    public function test_a_settings_page_shows_the_success_message_one_time(): void
    {
        $html = $this->actingAs($this->user)
            ->withSession(['success' => 'Widget settings saved.'])
            ->get('/settings/widget')
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Widget settings saved.'));
    }

    public function test_the_public_site_shows_the_success_message(): void
    {
        $this->withSession(['success' => 'Vote added'])
            ->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('Vote added');
    }

    public function test_the_public_site_shows_the_info_message(): void
    {
        $this->withSession(['info' => 'You have already voted'])
            ->get($this->siteUrl('/posts'))
            ->assertOk()
            ->assertSee('You have already voted');
    }

    private function siteUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }
}
