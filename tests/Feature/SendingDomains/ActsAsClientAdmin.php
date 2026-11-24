<?php

namespace Tests\Feature\SendingDomains;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

/**
 * Log in as an admin with full access to a new client, and select that client.
 */
trait ActsAsClientAdmin
{
    /**
     * @return array{0: User, 1: Client}
     */
    private function actingAdmin(): array
    {
        $user = User::factory()->create([
            'onboarding' => User::ONBOARDING_COMPLETED,
        ]);
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        Contact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'email' => $user->email,
        ]);

        $this->actingAs($user)->withSession(['fp_client_id' => $client->id]);

        return [$user, $client];
    }
}
