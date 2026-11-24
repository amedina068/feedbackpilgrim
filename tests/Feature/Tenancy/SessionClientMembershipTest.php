<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The session keeps the current client after the owner removes a user from
 * that client. The clientId middleware must not trust the session value.
 */
class SessionClientMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_removed_member_cannot_act_on_the_client_in_the_session(): void
    {
        $own = Client::factory()->create();
        $removed = Client::factory()->create();
        $user = User::factory()->create();
        $own->users()->attach($user);
        $user->adminContactFor($own);

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $removed->id])
            ->post('/settings/api-keys')
            ->assertSessionHas('fp_client_id', $own->id);

        $this->assertSame(0, PersonalAccessToken::query()->where('client_id', $removed->id)->count());
        $this->assertSame(1, PersonalAccessToken::query()->where('client_id', $own->id)->count());
    }

    public function test_a_user_with_no_client_left_gets_no_current_client(): void
    {
        $removed = Client::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $removed->id])
            ->post('/settings/api-keys');

        $this->assertSame(0, PersonalAccessToken::query()->where('client_id', $removed->id)->count());
    }

    public function test_a_member_keeps_the_client_in_the_session(): void
    {
        $first = Client::factory()->create();
        $second = Client::factory()->create();
        $user = User::factory()->create();
        $first->users()->attach($user);
        $second->users()->attach($user);
        $user->adminContactFor($second);

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $second->id])
            ->post('/settings/api-keys')
            ->assertSessionHas('fp_client_id', $second->id);

        $this->assertSame(1, PersonalAccessToken::query()->where('client_id', $second->id)->count());
    }
}
