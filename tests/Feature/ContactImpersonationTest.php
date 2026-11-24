<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_admin_is_redirected_to_the_contact_signed_login_url(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->get("/contacts/{$contact->id}/impersonate");

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString("/authenticate/{$contact->uuid}", $location);
        $this->assertStringContainsString($client->slug, $location);
    }

    public function test_admin_cannot_impersonate_a_contact_from_another_client(): void
    {
        $client = Client::factory()->create();
        $user = $this->actingAdmin($client);
        $foreign = Contact::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($user)
            ->get("/contacts/{$foreign->id}/impersonate")
            ->assertNotFound();
    }

    public function test_guests_cannot_impersonate(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $this->get("/contacts/{$contact->id}/impersonate")->assertRedirect('/login');
    }
}
