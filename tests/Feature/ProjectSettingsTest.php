<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create(['name' => 'Acme']);
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
    }

    public function test_the_settings_have_a_project_page(): void
    {
        $this->client->settings()->create(['name' => ClientSetting::NAME_DOMAIN, 'value' => 'acme.example.com']);

        $this->actingAs($this->user)
            ->get('/settings/project')
            ->assertOk()
            ->assertSee('action="'.route('client.update').'"', false)
            ->assertSee('value="Acme"', false)
            ->assertSee('value="acme.example.com"', false)
            ->assertSee('name="logo"', false);

        $this->actingAs($this->user)
            ->get('/settings/api-tokens')
            ->assertSee(route('settings.project'));
    }

    public function test_an_admin_renames_the_project_from_the_page(): void
    {
        $this->actingAs($this->user)
            ->from('/settings/project')
            ->patch(route('client.update'), ['name' => 'Acme Labs', 'domain' => 'acme.example.com'])
            ->assertRedirect('/settings/project')
            ->assertSessionHasNoErrors();

        $this->assertSame('Acme Labs', $this->client->fresh()->name);
        $this->assertSame('acme.example.com', $this->client->settings()->domain()->value('value'));
    }

    public function test_an_empty_website_removes_the_back_link(): void
    {
        $this->client->settings()->create(['name' => ClientSetting::NAME_DOMAIN, 'value' => 'acme.example.com']);

        $this->actingAs($this->user)
            ->from('/settings/project')
            ->patch(route('client.update'), ['name' => 'Acme', 'domain' => ''])
            ->assertRedirect('/settings/project')
            ->assertSessionHasNoErrors();

        $this->assertNull($this->client->settings()->domain()->value('value'));
    }

    public function test_a_website_longer_than_255_characters_is_refused(): void
    {
        $this->actingAs($this->user)
            ->from('/settings/project')
            ->patch(route('client.update'), ['name' => 'Acme', 'domain' => str_repeat('a', 252).'.com'])
            ->assertSessionHasErrors('domain');
    }
}
