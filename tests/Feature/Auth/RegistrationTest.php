<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.registration_enabled' => true]);

        $this->completeInstanceSetup();
    }

    public function test_registration_is_disabled_by_default(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_password_must_have_at_least_eight_characters(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/onboarding/project');
    }

    public function test_registration_starts_user_in_client_onboarding_without_a_client(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::firstWhere('email', 'test@example.com');

        $this->assertNotNull($user);
        $this->assertSame(User::ONBOARDING_CLIENT, $user->onboarding);
        $this->assertCount(0, $user->clients);
    }

    public function test_onboarding_project_page_loads_for_a_freshly_registered_user(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response = $this->get('/onboarding/project');

        $response->assertStatus(200);
    }

    public function test_onboarding_creates_a_client_and_links_it_to_the_user(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response = $this->post('/onboarding/project', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
        ]);

        $response->assertRedirect('dashboard');

        $client = Client::firstWhere('slug', 'acme');

        $this->assertNotNull($client);
        $this->assertSame('Acme Inc', $client->name);

        $user = User::firstWhere('email', 'test@example.com');

        $this->assertTrue($user->clients()->where('clients.id', $client->id)->exists());
        $this->assertSame(User::ONBOARDING_COMPLETED, $user->onboarding);
    }
}
