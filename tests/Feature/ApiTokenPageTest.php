<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $this->user->id]);
    }

    public function test_a_token_gets_the_name_that_the_user_types(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/api-keys', ['name' => 'Zapier'])
            ->assertSessionHas('api_success', 'API token "Zapier" created.')
            ->assertSessionHas('new_token');

        $this->assertSame(['Zapier'], $this->user->tokens()->pluck('name')->all());
    }

    public function test_a_token_without_a_name_gets_a_default_name(): void
    {
        $this->actingAs($this->user)->post('/settings/api-keys');

        $this->assertSame(['API token'], $this->user->tokens()->pluck('name')->all());
    }

    public function test_a_name_that_is_too_long_is_refused(): void
    {
        $this->actingAs($this->user)
            ->post('/settings/api-keys', ['name' => str_repeat('a', 101)])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_the_new_token_shows_in_full_and_can_be_selected(): void
    {
        $this->actingAs($this->user)
            ->withSession(['new_token' => '1|abcdefghijklmnopqrstuvwxyz0123456789'])
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertSee('Copy your token now. It will not be shown again.')
            ->assertSee('break-all select-all', false)
            ->assertSee('label="Copy token"', false);
    }

    public function test_the_page_uses_one_term_for_tokens_and_projects(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertSee('Each token is scoped to this project.')
            ->assertDontSee('scoped to this client')
            ->assertDontSee('Keys &amp; MCP access', false)
            ->assertSee('<label for="token-name"', false);
    }
}
