<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard has a "Log out" button in the sidebar and in the mobile menu.
 */
class DashboardLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_the_dashboard_shows_a_log_out_button_in_the_sidebar_and_the_mobile_menu(): void
    {
        $html = $this->actingAs($this->actingAdmin())->get('/dashboard')->assertOk()->getContent();

        $form = '#<form method="POST" action="'.preg_quote(route('logout'), '#').'">\s*<input type="hidden" name="_token"[^>]*>\s*<button type="submit"[^>]*>.*?Log out\s*</button>#s';

        $this->assertSame(2, preg_match_all($form, $html));
    }

    public function test_the_log_out_button_ends_the_session(): void
    {
        $this->actingAs($this->actingAdmin())->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
