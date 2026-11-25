<?php

namespace Tests\Feature\Agent;

use App\Models\Client;
use App\Models\User;
use App\Support\LoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The one-time login link of `fp:install` and of the `invite-user` admin tool.
 */
class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['email' => 'jane@example.com', 'onboarding' => User::ONBOARDING_COMPLETED]);
        $owner->forceFill(['is_owner' => true])->save();
        $client = Client::factory()->create();
        $owner->clients()->attach($client);
        $owner->adminContactFor($client);

        return $owner;
    }

    public function test_a_link_logs_the_user_in_and_opens_the_profile(): void
    {
        $owner = $this->owner();

        $this->get(LoginLink::make($owner, now()->addHour()))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas(LoginLink::SESSION_KEY, true);

        $this->assertAuthenticatedAs($owner);
    }

    public function test_a_link_works_one_time(): void
    {
        $owner = $this->owner();
        $link = LoginLink::make($owner, now()->addHour());

        $this->get($link)->assertRedirect(route('profile.edit'));
        $this->post('/logout');
        $this->assertGuest();

        $this->get($link)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_expired_link_does_not_log_in(): void
    {
        $link = LoginLink::make($this->owner(), now()->addHour());

        $this->travel(61)->minutes();

        $this->get($link)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_changed_link_does_not_log_in(): void
    {
        $owner = $this->owner();
        $other = User::factory()->create();
        $link = LoginLink::make($owner, now()->addHour());

        $this->get(str_replace('/login/link/'.$owner->id, '/login/link/'.$other->id, $link))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_deactivated_user_cannot_use_a_link(): void
    {
        $owner = $this->owner();
        $link = LoginLink::make($owner, now()->addHour());
        $owner->deactivate();

        $this->get($link)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_after_a_link_login_the_user_sets_a_password_without_the_current_one_one_time(): void
    {
        $owner = $this->owner();
        $this->get(LoginLink::make($owner, now()->addHour()));

        $this->put('/password', [
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-new-long-password', $owner->fresh()->password));

        $this->put('/password', [
            'password' => 'another-long-password',
            'password_confirmation' => 'another-long-password',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');
    }

    public function test_the_profile_page_asks_for_a_password_after_a_link_login(): void
    {
        $owner = $this->owner();
        $this->get(LoginLink::make($owner, now()->addHour()));

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('You logged in with a one-time link')
            ->assertDontSee('update_password_current_password');
    }
}
