<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The admin profile routes run behind the `clientId` middleware, which
     * resolves the active client from the session (falling back to the user's
     * first client). Seed the session so the middleware has a client to work
     * with, mirroring an onboarded admin.
     */
    private function actingAsAdmin(User $user): self
    {
        $client = Client::factory()->create();

        return $this->actingAs($user)->withSession(['fp_client_id' => $client->id]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $client->users()->attach($user);
        // The admin layout's view composer resolves the user's contact for the
        // active client, so an onboarded admin needs one to render the page.
        Contact::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin($user)->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin($user)->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin($user)->delete('/profile', [
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsAdmin($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
