<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Before billing was removed, a user that created a client stopped at the
 * "pricing" onboarding step until checkout. Checkout created the admin
 * contact. The migration moves these users to "completed" and creates the
 * admin contact for each of their clients.
 */
class CompletePricingOnboardingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_16_000001_complete_pricing_onboarding.php';

    private function runMigration(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->up();
    }

    public function test_pricing_users_complete_onboarding_and_get_admin_contacts(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);
        User::query()->whereKey($user->id)->update(['onboarding' => 'pricing']);
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $this->runMigration();

        $this->assertSame(User::ONBOARDING_COMPLETED, $user->fresh()->onboarding);
        $contact = Contact::query()->where('user_id', $user->id)->where('client_id', $client->id)->firstOrFail();
        $this->assertSame($user->email, $contact->email);
        $this->assertSame('admin', json_decode($contact->metadata, true)['role']);
    }

    public function test_existing_admin_contact_is_kept(): void
    {
        $user = User::factory()->create();
        User::query()->whereKey($user->id)->update(['onboarding' => 'pricing']);
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        $existing = Contact::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);

        $this->runMigration();

        $this->assertSame(1, Contact::query()->where('user_id', $user->id)->count());
        $this->assertTrue($existing->is(Contact::query()->where('user_id', $user->id)->first()));
    }

    public function test_other_users_are_not_changed(): void
    {
        $onboarding = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);
        $completed = User::factory()->create();
        $client = Client::factory()->create();
        $completed->clients()->attach($client);

        $this->runMigration();

        $this->assertSame(User::ONBOARDING_CLIENT, $onboarding->fresh()->onboarding);
        $this->assertSame(0, Contact::query()->count());
    }
}
