<?php

namespace Tests\Feature;

use App\Auth\ActiveUserProvider;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * A deactivated user keeps every row, and loses every way in. Each test below
 * covers one entry point that authenticates as the user, or one public surface
 * of a project that only deactivated users run.
 */
class DeactivatedUserTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('set')->andReturn(true);
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->client = Client::factory()->create(['require_identity_verification' => false]);
        $user->clients()->attach($this->client);
        $user->adminContactFor($this->client);

        return $user;
    }

    private function publicUrl(string $path): string
    {
        return 'http://'.$this->client->slug.'.'.config('app.domain').$path;
    }

    /**
     * Drop the guards that the earlier request built, so that the next request
     * authenticates from scratch the way a new HTTP request does.
     */
    private function freshRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function apiToken(User $user): string
    {
        $token = $user->createToken('cli', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        return $token->plainTextToken;
    }

    // ------------------------------------------------------------ the model

    public function test_deactivate_and_reactivate_toggle_the_timestamp(): void
    {
        $user = $this->user();
        $this->assertFalse($user->isDeactivated());

        $user->deactivate();
        $this->assertTrue($user->fresh()->isDeactivated());

        $user->reactivate();
        $this->assertFalse($user->fresh()->isDeactivated());
    }

    public function test_the_user_provider_does_not_return_a_deactivated_user(): void
    {
        $user = $this->user(['deactivated_at' => now()]);
        $user->forceFill(['remember_token' => 'remember-me'])->save();

        $provider = Auth::createUserProvider('users');

        $this->assertInstanceOf(ActiveUserProvider::class, $provider);
        $this->assertNull($provider->retrieveById($user->id));
        $this->assertNull($provider->retrieveByToken($user->id, 'remember-me'));

        $user->reactivate();
        $this->assertSame($user->id, $provider->retrieveById($user->id)?->id);
        $this->assertSame($user->id, $provider->retrieveByToken($user->id, 'remember-me')?->id);
    }

    // ------------------------------------------------------------ the login form

    public function test_a_deactivated_user_cannot_log_in_and_reads_why(): void
    {
        $user = $this->user(['deactivated_at' => now()]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => User::DEACTIVATED_MESSAGE]);
        $this->assertGuest();
    }

    public function test_a_wrong_password_does_not_tell_that_the_account_is_deactivated(): void
    {
        $user = $this->user(['deactivated_at' => now()]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_a_reactivated_user_can_log_in_again(): void
    {
        $user = $this->user(['deactivated_at' => now()]);
        $user->reactivate();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_page_shows_the_message(): void
    {
        $this->completeInstanceSetup();

        $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['email' => User::DEACTIVATED_MESSAGE]))])
            ->get('/login')
            ->assertSee(User::DEACTIVATED_MESSAGE);
    }

    // ------------------------------------------------------------ an open web session

    public function test_an_open_session_ends_at_the_next_request(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/posts')->assertOk();

        $user->deactivate();

        $this->get('/posts')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => User::DEACTIVATED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_a_stored_session_of_a_deactivated_user_is_logged_out_with_the_message(): void
    {
        $user = $this->user(['deactivated_at' => now()]);
        $sessionKey = Auth::guard('web')->getName();

        $this->withSession([$sessionKey => $user->id])
            ->get('/posts')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => User::DEACTIVATED_MESSAGE])
            ->assertSessionMissing($sessionKey);

        $this->assertGuest();
    }

    public function test_a_json_request_from_a_deactivated_session_gets_a_401(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $user->deactivate();

        $this->getJson('/posts')
            ->assertUnauthorized()
            ->assertJson(['message' => User::DEACTIVATED_MESSAGE]);
    }

    public function test_a_session_for_an_active_user_is_not_touched(): void
    {
        $user = $this->user();
        $sessionKey = Auth::guard('web')->getName();

        $this->withSession([$sessionKey => $user->id])->get('/posts')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    // ------------------------------------------------------------ the remember-me cookie

    public function test_a_remember_me_cookie_stops_working(): void
    {
        $user = $this->user();
        $user->forceFill(['remember_token' => 'remember-me-token'])->save();
        $cookieName = Auth::guard('web')->getRecallerName();
        $cookieValue = $user->id.'|remember-me-token|'.$user->getAuthPassword();

        $this->withCookie($cookieName, $cookieValue)->get('/posts')->assertOk();
        $this->assertAuthenticatedAs($user);

        $user->deactivate();
        $this->freshRequest();
        $this->flushSession();

        $this->withCookie($cookieName, $cookieValue)->get('/posts')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ------------------------------------------------------------ Sanctum API tokens

    public function test_a_sanctum_token_stops_working_and_works_again_after_reactivation(): void
    {
        $user = $this->user();
        $token = $this->apiToken($user);

        $this->withToken($token)->getJson('/api/public/v1/posts')->assertOk();

        $user->deactivate();
        $this->freshRequest();
        $this->withToken($token)->getJson('/api/public/v1/posts')->assertUnauthorized();

        $user->reactivate();
        $this->freshRequest();
        $this->withToken($token)->getJson('/api/public/v1/posts')->assertOk();
    }

    // ------------------------------------------------------------ links and sessions of the admin contact

    public function test_the_signed_link_of_the_admin_contact_stops_working(): void
    {
        $user = $this->user();
        $url = $user->adminContactFor($this->client)->generateAuthUrl();

        $this->get($url)->assertRedirect();
        $this->assertAuthenticatedAs($user->adminContactFor($this->client), 'contact');

        Auth::guard('contact')->logout();
        $user->deactivate();
        $this->freshRequest();

        $this->get($url)->assertNotFound();
        $this->assertGuest('contact');
    }

    public function test_a_public_site_session_of_the_admin_contact_ends(): void
    {
        $owner = User::factory()->create();
        $user = $this->user();
        $owner->clients()->attach($this->client);
        $contact = $user->adminContactFor($this->client);
        $sessionKey = Auth::guard('contact')->getName();

        $this->withSession([$sessionKey => $contact->id])->get($this->publicUrl('/posts/create'))->assertOk();

        $user->deactivate();
        $this->freshRequest();

        $this->withSession([$sessionKey => $contact->id])->get($this->publicUrl('/posts/create'))->assertRedirect();
        $this->assertGuest('contact');
    }

    public function test_a_normal_contact_is_not_touched(): void
    {
        $this->user();
        $contact = Contact::factory()->create(['client_id' => $this->client->id]);

        $this->get($contact->generateAuthUrl())->assertRedirect();
        $this->assertAuthenticatedAs($contact, 'contact');
    }

    // ------------------------------------------------------------ the public surfaces of the project

    public function test_the_widget_of_a_project_with_only_deactivated_users_stops(): void
    {
        $user = $this->user();
        $init = fn () => $this->withToken($this->client->publishable_key)
            ->postJson('/api/v1/init', ['contact' => ['email' => 'jane@example.com', 'name' => 'Jane']]);

        $init()->assertOk();

        $user->deactivate();

        $init()->assertUnauthorized();
    }

    public function test_the_public_site_iframes_and_widget_api_of_that_project_stop(): void
    {
        $user = $this->user();

        $this->get($this->publicUrl('/'))->assertOk();
        $this->get($this->publicUrl('/iframe/release-notes'))->assertOk();
        $this->getJson($this->publicUrl('/api/v1/release-notes'))->assertOk();

        $user->deactivate();

        $this->get($this->publicUrl('/'))->assertNotFound();
        $this->get($this->publicUrl('/iframe/release-notes'))->assertNotFound();
        $this->getJson($this->publicUrl('/api/v1/release-notes'))->assertNotFound();

        $user->reactivate();

        $this->get($this->publicUrl('/iframe/release-notes'))->assertOk();
    }

    public function test_a_project_with_another_active_user_keeps_its_public_surfaces(): void
    {
        $user = $this->user();
        $teammate = User::factory()->create();
        $teammate->clients()->attach($this->client);

        $user->deactivate();

        $this->get($this->publicUrl('/iframe/release-notes'))->assertOk();
        $this->withToken($this->client->publishable_key)
            ->postJson('/api/v1/init', ['contact' => ['email' => 'jane@example.com', 'name' => 'Jane']])
            ->assertOk();
    }

    public function test_deactivation_keeps_the_data_of_the_user(): void
    {
        $user = $this->user();
        $this->apiToken($user);

        $user->deactivate();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseHas('client_user', ['user_id' => $user->id, 'client_id' => $this->client->id]);
        $this->assertDatabaseHas('contacts', ['user_id' => $user->id]);
    }
}
