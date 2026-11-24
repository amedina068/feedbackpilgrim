<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin dashboard sits behind `auth` + `clientId` + `hasAccess`. These pin
 * the onboarding gate and authentication redirects so the upgrade can't quietly
 * change who reaches the dashboard.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_admin_without_access_is_sent_to_onboarding(): void
    {
        $user = User::factory()->create([
            'onboarding' => User::ONBOARDING_CLIENT,
        ]);
        $client = Client::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->get('/dashboard');

        $response->assertRedirect('/onboarding/project');
    }
}
