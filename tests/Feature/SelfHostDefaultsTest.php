<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A self-hosted instance must not send data to third-party services that
 * the operator did not configure, and must not show a hardcoded domain.
 */
class SelfHostDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_log_the_service_worker_to_the_console(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        $user->adminContactFor($client);

        $this->actingAs($user)
            ->get('/posts')
            ->assertOk()
            ->assertDontSee('console.log', false);
    }

    public function test_default_log_stack_does_not_use_rollbar(): void
    {
        $this->assertNotContains('rollbar', config('logging.channels.stack.channels'));
    }

    public function test_default_mail_host_is_not_a_third_party_service(): void
    {
        $this->assertStringNotContainsString('mailgun', (string) config('mail.mailers.smtp.host'));
    }

    public function test_onboarding_shows_the_configured_domain(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)
            ->get('/onboarding/project')
            ->assertOk()
            ->assertSee('.'.config('app.domain'))
            ->assertDontSee('feedbackpilgrim.com');
    }
}
