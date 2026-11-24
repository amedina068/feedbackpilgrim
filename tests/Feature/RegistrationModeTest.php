<?php

namespace Tests\Feature;

use App\Support\InstanceSettings;
use App\Support\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The owner sets the registration mode in the admin area. The database value
 * wins. REGISTRATION_ENABLED gives the mode only when the database has none.
 */
class RegistrationModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->completeInstanceSetup();
    }

    private function signUp(string $email)
    {
        return $this->post('/register', [
            'name' => 'Jane',
            'email' => $email,
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ]);
    }

    public function test_the_env_switch_is_the_default_when_the_database_has_no_value(): void
    {
        config(['app.registration_enabled' => false]);
        $this->assertSame(Registration::CLOSED, Registration::mode());
        $this->assertFalse(Registration::isOpen());

        config(['app.registration_enabled' => true]);
        $this->assertSame(Registration::OPEN, Registration::mode());
        $this->assertTrue(Registration::isOpen());
    }

    public function test_the_database_value_wins_over_the_env_switch(): void
    {
        config(['app.registration_enabled' => true]);
        Registration::setMode(Registration::CLOSED);
        $this->assertFalse(Registration::isOpen());

        config(['app.registration_enabled' => false]);
        Registration::setMode(Registration::OPEN);
        $this->assertTrue(Registration::isOpen());
    }

    public function test_an_unknown_value_in_the_database_falls_back_to_the_env_switch(): void
    {
        config(['app.registration_enabled' => false]);
        DB::table('instance_settings')->insert(['key' => Registration::SETTING, 'value' => 'garbage']);
        app(InstanceSettings::class)->flush();

        $this->assertSame(Registration::CLOSED, Registration::mode());
    }

    public function test_an_unknown_mode_cannot_be_stored(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Registration::setMode('everyone');
    }

    public function test_the_read_is_cached_and_a_write_refreshes_it(): void
    {
        config(['app.registration_enabled' => false]);
        Registration::setMode(Registration::OPEN);
        $this->assertTrue(Registration::isOpen());

        DB::enableQueryLog();
        Registration::isOpen();
        Registration::isOpen();
        $this->assertCount(0, DB::getQueryLog());

        Registration::setMode(Registration::CLOSED);
        $this->assertFalse(Registration::isOpen());
    }

    public function test_open_mode_allows_a_sign_up_even_when_the_env_switch_is_off(): void
    {
        config(['app.registration_enabled' => false]);
        Registration::setMode(Registration::OPEN);

        $this->get('/register')->assertOk();
        $this->signUp('jane@example.com')->assertRedirect('/onboarding/project');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_closed_mode_refuses_a_sign_up_even_when_the_env_switch_is_on(): void
    {
        config(['app.registration_enabled' => true]);
        Registration::setMode(Registration::CLOSED);

        $this->get('/register')->assertNotFound();
        $this->signUp('mallory@example.com')->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'mallory@example.com']);
    }

    public function test_the_sign_up_links_follow_the_mode(): void
    {
        config(['app.registration_enabled' => false]);

        Registration::setMode(Registration::CLOSED);
        $this->get('/')->assertDontSee('Create an account');

        Registration::setMode(Registration::OPEN);
        $this->get('/')->assertSee('Create an account');
    }
}
