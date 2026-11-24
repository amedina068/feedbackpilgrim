<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The admin area reads the current client from the session only. A
 * `fp_client_id` value in the query string or in the body has no effect.
 */
class CurrentClientFromSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Client, 1: User}
     */
    private function tenant(): array
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $client->users()->attach($user);
        $user->adminContactFor($client);

        return [$client, $user];
    }

    public function test_api_key_is_minted_for_the_session_client_not_the_query_client(): void
    {
        [$own, $user] = $this->tenant();
        [$other] = $this->tenant();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $own->id])
            ->post('/settings/api-keys?fp_client_id='.$other->id, ['fp_client_id' => $other->id])
            ->assertSessionHas('new_token');

        $this->assertSame(0, PersonalAccessToken::query()->where('client_id', $other->id)->count());
        $this->assertSame(1, PersonalAccessToken::query()->where('client_id', $own->id)->count());
    }

    public function test_api_key_of_another_client_cannot_be_deleted_through_the_query(): void
    {
        [$own, $user] = $this->tenant();
        [$other, $otherUser] = $this->tenant();
        $token = $otherUser->createToken('API Key', ['*'])->accessToken;
        $token->forceFill(['client_id' => $other->id])->save();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $own->id])
            ->delete('/settings/api-keys/'.$token->id.'?fp_client_id='.$other->id)
            ->assertNotFound();

        $this->assertModelExists($token);
    }

    public function test_api_key_of_the_session_client_can_be_deleted(): void
    {
        [$own, $user] = $this->tenant();
        $token = $user->createToken('API Key', ['*'])->accessToken;
        $token->forceFill(['client_id' => $own->id])->save();

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $own->id])
            ->delete('/settings/api-keys/'.$token->id)
            ->assertRedirect();

        $this->assertModelMissing($token);
    }

    public function test_widget_integration_and_testimonial_settings_of_another_client_do_not_change(): void
    {
        [$own, $user] = $this->tenant();
        [$other] = $this->tenant();
        $oldSecret = $other->fresh()->identity_secret;
        $query = '?fp_client_id='.$other->id;

        $this->actingAs($user)->withSession(['fp_client_id' => $own->id])
            ->patch('/settings/widget'.$query, ['require_identity_verification' => 0])
            ->assertRedirect();
        $this->actingAs($user)->withSession(['fp_client_id' => $own->id])
            ->post('/settings/widget/identity-secret'.$query)
            ->assertRedirect();
        $this->actingAs($user)->withSession(['fp_client_id' => $own->id])
            ->patch('/settings/integrations'.$query, ['slack_webhook_url' => 'https://hooks.slack.com/services/T/B/C'])
            ->assertRedirect();
        $this->actingAs($user)->withSession(['fp_client_id' => $own->id])
            ->patch('/settings'.$query, ['testimonial_automation' => 1, 'testimonial_email_body' => 'Changed text'])
            ->assertRedirect();

        $other->refresh();
        $this->assertTrue($other->require_identity_verification);
        $this->assertSame($oldSecret, $other->identity_secret);
        $this->assertNull($other->slack_webhook_url);
        $this->assertDatabaseMissing('client_settings', ['client_id' => $other->id, 'name' => ClientSetting::NAME_TESTIMONIAL_EMAIL_BODY, 'value' => 'Changed text']);

        $own->refresh();
        $this->assertFalse($own->require_identity_verification);
        $this->assertSame('https://hooks.slack.com/services/T/B/C', $own->slack_webhook_url);
        $this->assertDatabaseHas('client_settings', ['client_id' => $own->id, 'name' => ClientSetting::NAME_TESTIMONIAL_EMAIL_BODY, 'value' => 'Changed text']);
    }

    public function test_api_tokens_page_lists_only_the_session_client_tokens(): void
    {
        [$own, $user] = $this->tenant();
        [$other, $otherUser] = $this->tenant();
        $token = $otherUser->createToken('Other client key', ['*'])->accessToken;
        $token->forceFill(['client_id' => $other->id])->save();

        $this->actingAs($user)->withSession(['fp_client_id' => $own->id])
            ->get('/settings/api-tokens?fp_client_id='.$other->id)
            ->assertOk()
            ->assertDontSee('Other client key');
    }

    public function test_admin_pages_ignore_a_client_id_in_the_query(): void
    {
        [$own, $user] = $this->tenant();
        [$other] = $this->tenant();
        Contact::factory()->create(['client_id' => $other->id, 'email' => 'private@other-client.test']);

        $this->actingAs($user)->withSession(['fp_client_id' => $own->id])
            ->get('/contacts?fp_client_id='.$other->id)
            ->assertOk()
            ->assertDontSee('private@other-client.test');
    }
}
