<?php

namespace Tests\Feature\Auth;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Posts\CreatePostCommentTool;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Onboarding is the last step before the dashboard. It creates the first
 * client and the admin contact: the per-client identity of the dashboard user
 * that the public site login and the MCP tools use.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function onboard(User $user, string $subdomain = 'acme')
    {
        return $this->actingAs($user)->post('/onboarding/project', [
            'name' => 'Acme Inc',
            'subdomain' => $subdomain,
        ]);
    }

    public function test_onboarding_completes_and_opens_the_dashboard(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->onboard($user)->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertSame(User::ONBOARDING_COMPLETED, $user->onboarding);
        $this->assertTrue($user->hasAccess());
    }

    public function test_a_user_with_a_project_creates_a_second_project(): void
    {
        $first = Client::factory()->create();
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_COMPLETED]);
        $user->clients()->attach($first);

        $this->actingAs($user)
            ->get('/onboarding/project')
            ->assertOk()
            ->assertSee('href="/dashboard"', false);

        $this->onboard($user, 'second')->assertRedirect('/dashboard');

        $second = Client::query()->where('slug', 'second')->firstOrFail();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $user->clients()->pluck('clients.id')->all());
        $this->assertSame($second->id, session('fp_client_id'));
    }

    public function test_the_first_onboarding_has_no_cancel_link(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)
            ->get('/onboarding/project')
            ->assertOk()
            ->assertDontSee('href="/dashboard"', false);
    }

    public function test_onboarding_creates_the_admin_contact_for_the_new_client(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->onboard($user);

        $client = Client::where('slug', 'acme')->firstOrFail();
        $contact = Contact::query()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->firstOrFail();

        $this->assertSame($user->name, $contact->name);
        $this->assertSame($user->email, $contact->email);
        $this->assertSame('admin', json_decode($contact->metadata, true)['role']);
        $this->assertNotEmpty($contact->uuid);
    }

    public function test_onboarding_selects_the_new_client_in_the_session(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->onboard($user);

        $client = Client::where('slug', 'acme')->firstOrFail();
        $this->assertSame($client->id, session('fp_client_id'));
    }

    public function test_public_site_logs_in_the_admin_contact_after_onboarding(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->onboard($user);

        $client = Client::where('slug', 'acme')->firstOrFail();
        $adminContact = Contact::query()->where('user_id', $user->id)->firstOrFail();

        $this->get('http://acme.'.config('app.domain').'/')->assertOk();

        $this->assertAuthenticatedAs($adminContact, 'contact');
        $this->assertSame($client->id, $adminContact->client_id);
    }

    public function test_mcp_comment_uses_the_admin_contact_from_onboarding(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->onboard($user);

        $client = Client::where('slug', 'acme')->firstOrFail();
        $adminContact = Contact::query()->where('user_id', $user->id)->firstOrFail();
        $post = Post::factory()->create(['client_id' => $client->id]);

        $token = $user->createToken('mcp', ['*'])->accessToken;
        $token->client_id = $client->id;
        $token->save();
        $user->withAccessToken($token);

        PublicServer::actingAs($user)
            ->tool(CreatePostCommentTool::class, ['post_id' => $post->id, 'content' => 'Shipped.'])
            ->assertOk();

        $this->assertDatabaseHas('comments', ['commentable_id' => $post->id, 'contact_id' => $adminContact->id]);
        $this->assertSame(1, Contact::query()->where('user_id', $user->id)->count());
    }

    public function test_admin_contact_for_is_idempotent(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $first = $user->adminContactFor($client);
        $second = $user->adminContactFor($client);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Contact::query()->where('user_id', $user->id)->count());
    }
}
