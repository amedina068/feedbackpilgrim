<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTitleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    private function mobileTitle(string $title): string
    {
        return "text-sm/6 font-medium text-fp-hi\">{$title}</p>";
    }

    public function test_mobile_bar_reflects_the_current_section(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->get('/contacts')
            ->assertSee($this->mobileTitle('Contacts'), false);
    }

    public function test_mobile_bar_shows_boards_on_the_kanban(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->get('/board')
            ->assertSee($this->mobileTitle('Boards'), false);
    }

    public function test_the_sidebar_and_the_mobile_menu_mark_the_current_link(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $html = $this->actingAs($user)->get('/contacts')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#href="/contacts"\s+aria-current="page"#', $html);
    }

    public function test_the_menu_button_opens_the_mobile_menu_dialog(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);

        $this->actingAs($user)
            ->get('/contacts')
            ->assertOk()
            ->assertSee('<dialog', false)
            ->assertSee('id="admin-menu"', false)
            ->assertSee("document.getElementById('admin-menu').showModal()", false)
            ->assertSee('Close menu')
            ->assertSee('Your profile');
    }
}
