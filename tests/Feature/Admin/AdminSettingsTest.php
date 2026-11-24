<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['is_owner' => true]);
        $client = Client::factory()->create();
        $owner->clients()->attach($client);
        $owner->adminContactFor($client);

        return $owner;
    }

    public function test_the_settings_page_shows_both_modes_and_the_current_one(): void
    {
        config(['app.registration_enabled' => false]);

        $response = $this->actingAs($this->owner())->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('value="open"', false);
        $response->assertSee('value="closed"', false);
        $response->assertSee('Anyone can sign up');
        $response->assertSee('Nobody can sign up');
        $response->assertSee('value="closed" checked', false);
    }

    public function test_the_owner_changes_the_registration_mode(): void
    {
        config(['app.registration_enabled' => false]);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put('/admin/settings', ['registration_mode' => 'open'])
            ->assertRedirect('/admin/settings')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('instance_settings', ['key' => 'registration_mode', 'value' => 'open']);
        $this->assertTrue(Registration::isOpen());

        $this->actingAs($owner)
            ->put('/admin/settings', ['registration_mode' => 'closed'])
            ->assertRedirect('/admin/settings');

        $this->assertFalse(Registration::isOpen());
    }

    public function test_an_unknown_mode_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->from('/admin/settings')
            ->put('/admin/settings', ['registration_mode' => 'everyone'])
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('registration_mode');

        $this->assertDatabaseMissing('instance_settings', ['key' => 'registration_mode']);
    }
}
