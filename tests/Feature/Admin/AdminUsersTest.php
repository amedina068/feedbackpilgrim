<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create([
            'is_owner' => true,
            'name' => 'Olive Owner',
            'email' => 'owner@example.com',
        ]);
        $client = Client::factory()->create(['name' => 'Olive Board']);
        $owner->clients()->attach($client);
        $owner->adminContactFor($client);

        return $owner;
    }

    public function test_the_list_shows_every_user_with_projects_and_status(): void
    {
        $owner = $this->owner();
        $ada = User::factory()->create(['name' => 'Ada Active', 'email' => 'ada@example.com']);
        $ada->clients()->attach(Client::factory()->create(['name' => 'Acme Roadmap']));
        User::factory()->create(['name' => 'Dan Gone', 'email' => 'dan@example.com', 'deactivated_at' => now()]);

        $response = $this->actingAs($owner)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('Ada Active');
        $response->assertSee('ada@example.com');
        $response->assertSee('Acme Roadmap');
        $response->assertSee('Dan Gone');
        $response->assertSee('Deactivated');
        $response->assertSee('Owner');
    }

    public function test_the_list_is_paginated_with_the_newest_users_first(): void
    {
        $owner = $this->owner();
        User::factory()->count(30)->create();
        User::factory()->create(['name' => 'Zed Last-Created']);

        $this->actingAs($owner)->get('/admin/users')
            ->assertOk()
            ->assertSee('Zed Last-Created')
            ->assertSee('page=2', false);

        $this->actingAs($owner)->get('/admin/users?page=2')
            ->assertOk()
            ->assertDontSee('Zed Last-Created')
            ->assertSee('Olive Owner');
    }

    public function test_the_search_matches_the_name_or_the_email(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        User::factory()->create(['name' => 'Alan Turing', 'email' => 'enigma@example.org']);

        $this->actingAs($owner)->get('/admin/users?search=hopper')
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertDontSee('Alan Turing');

        $this->actingAs($owner)->get('/admin/users?search=enigma@')
            ->assertOk()
            ->assertSee('Alan Turing')
            ->assertDontSee('Grace Hopper');

        $this->actingAs($owner)->get('/admin/users?search=nobody-matches')
            ->assertOk()
            ->assertDontSee('Grace Hopper')
            ->assertSee('No user matches');
    }

    public function test_the_search_treats_wildcards_as_text(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Grace Hopper']);

        $this->actingAs($owner)->get('/admin/users?search=%25')
            ->assertOk()
            ->assertDontSee('Grace Hopper');
    }

    public function test_the_owner_deactivates_a_user(): void
    {
        $owner = $this->owner();
        $user = User::factory()->create();

        $this->actingAs($owner)
            ->from('/admin/users')
            ->post("/admin/users/{$user->id}/deactivation")
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->isDeactivated());
    }

    public function test_the_owner_reactivates_a_user(): void
    {
        $owner = $this->owner();
        $user = User::factory()->create(['deactivated_at' => now()]);

        $this->actingAs($owner)
            ->from('/admin/users')
            ->delete("/admin/users/{$user->id}/deactivation")
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status');

        $this->assertFalse($user->fresh()->isDeactivated());
    }

    public function test_the_owner_cannot_deactivate_an_owner(): void
    {
        $owner = $this->owner();
        $otherOwner = User::factory()->create(['is_owner' => true]);

        $this->actingAs($owner)->post("/admin/users/{$otherOwner->id}/deactivation")->assertForbidden();
        $this->actingAs($owner)->post("/admin/users/{$owner->id}/deactivation")->assertForbidden();

        $this->assertFalse($otherOwner->fresh()->isDeactivated());
        $this->assertFalse($owner->fresh()->isDeactivated());
    }

    public function test_the_list_offers_the_right_action_for_each_status(): void
    {
        $owner = $this->owner();
        $active = User::factory()->create();
        $inactive = User::factory()->create(['deactivated_at' => now()]);

        $response = $this->actingAs($owner)->get('/admin/users');

        $response->assertSee('action="'.url("/admin/users/{$active->id}/deactivation").'"', false);
        $response->assertSee('action="'.url("/admin/users/{$inactive->id}/deactivation").'"', false);
        // The owner row has no form.
        $response->assertDontSee('action="'.url("/admin/users/{$owner->id}/deactivation").'"', false);
        // Each form asks for a confirmation before it sends.
        $response->assertSee('onsubmit="return confirm(', false);
        $response->assertSee('Reactivate');
        $response->assertSee('Deactivate');
    }
}
