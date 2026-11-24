<?php

namespace Tests\Feature;

use App\Jobs\SyncContactAttributes;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactAttribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContactAttributeTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(): Client
    {
        return Client::factory()->createQuietly();
    }

    private function createAuthenticatedUser(Client $client): User
    {
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        return $user;
    }

    public function test_observer_dispatches_job_when_contact_created_with_metadata(): void
    {
        Queue::fake();

        $client = $this->createClient();

        Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode(['plan' => 'pro']),
        ]);

        Queue::assertPushed(SyncContactAttributes::class);
    }

    public function test_observer_dispatches_job_when_metadata_changes(): void
    {
        $client = $this->createClient();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode(['plan' => 'pro']),
        ]);

        Queue::fake();

        $contact->update(['metadata' => json_encode(['plan' => 'enterprise'])]);

        Queue::assertPushed(SyncContactAttributes::class);
    }

    public function test_observer_does_not_dispatch_job_when_metadata_unchanged(): void
    {
        $client = $this->createClient();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode(['plan' => 'pro']),
        ]);

        Queue::fake();

        $contact->update(['name' => 'New Name']);

        Queue::assertNotPushed(SyncContactAttributes::class);
    }

    public function test_job_creates_contact_attributes_from_metadata(): void
    {
        $client = $this->createClient();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode(['plan' => 'pro', 'company' => 'Acme']),
        ]);

        // Clear any attributes created by the observer (sync queue)
        ContactAttribute::query()->delete();

        (new SyncContactAttributes($contact))->handle();

        $this->assertDatabaseHas('contact_attributes', [
            'client_id' => $client->id,
            'name' => 'plan',
        ]);
        $this->assertDatabaseHas('contact_attributes', [
            'client_id' => $client->id,
            'name' => 'company',
        ]);
    }

    public function test_job_excludes_pilgrim_and_avatar_fields(): void
    {
        $client = $this->createClient();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode([
                'plan' => 'pro',
                'avatar' => 'https://example.com/avatar.png',
                'pilgrim_last_login' => '2026-01-01',
            ]),
        ]);

        ContactAttribute::query()->delete();

        (new SyncContactAttributes($contact))->handle();

        $this->assertDatabaseHas('contact_attributes', ['name' => 'plan']);
        $this->assertDatabaseMissing('contact_attributes', ['name' => 'avatar']);
        $this->assertDatabaseMissing('contact_attributes', ['name' => 'pilgrim_last_login']);
    }

    public function test_job_does_not_duplicate_attributes(): void
    {
        $client = $this->createClient();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode(['plan' => 'pro']),
        ]);

        ContactAttribute::query()->delete();
        ContactAttribute::create(['client_id' => $client->id, 'name' => 'plan']);

        (new SyncContactAttributes($contact))->handle();

        $this->assertCount(1, ContactAttribute::where('client_id', $client->id)->where('name', 'plan')->get());
    }

    public function test_init_creates_contact_attributes_via_observer(): void
    {
        $client = $this->createClient();
        $client->forceFill([
            'publishable_key' => Client::generatePublishableKey(),
            'require_identity_verification' => false,
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$client->publishable_key)
            ->postJson('/api/v1/init', [
                'contact' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'plan' => 'pro',
                    'company' => 'Acme',
                ],
            ]);

        $this->assertDatabaseHas('contact_attributes', [
            'client_id' => $client->id,
            'name' => 'plan',
        ]);
        $this->assertDatabaseHas('contact_attributes', [
            'client_id' => $client->id,
            'name' => 'company',
        ]);
    }

    public function test_contacts_index_displays_attribute_filters(): void
    {
        $client = $this->createClient();
        $user = $this->createAuthenticatedUser($client);

        ContactAttribute::create(['client_id' => $client->id, 'name' => 'plan']);

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->withServerVariables(['SERVER_NAME' => config('app.domain')])
            ->get('http://'.config('app.domain').'/contacts');

        $response->assertStatus(200);
        $response->assertSee('Plan');
        $response->assertSee('attributes[plan]', false);
    }

    public function test_contacts_index_filters_by_attribute(): void
    {
        $client = $this->createClient();
        $user = $this->createAuthenticatedUser($client);

        ContactAttribute::create(['client_id' => $client->id, 'name' => 'plan']);

        Contact::factory()->create([
            'client_id' => $client->id,
            'name' => 'Pro User',
            'metadata' => json_encode(['plan' => 'pro']),
        ]);
        Contact::factory()->create([
            'client_id' => $client->id,
            'name' => 'Free User',
            'metadata' => json_encode(['plan' => 'free']),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->withServerVariables(['SERVER_NAME' => config('app.domain')])
            ->get('http://'.config('app.domain').'/contacts?'.http_build_query(['attributes' => ['plan' => 'pro']]));

        $response->assertStatus(200);
        $response->assertSee('Pro User');
        $response->assertDontSee('Free User');
    }

    public function test_sync_command_creates_attributes_for_all_contacts(): void
    {
        $client = $this->createClient();

        Contact::withoutEvents(function () use ($client) {
            Contact::factory()->create([
                'client_id' => $client->id,
                'metadata' => json_encode(['plan' => 'pro', 'company' => 'Acme']),
            ]);
            Contact::factory()->create([
                'client_id' => $client->id,
                'metadata' => json_encode(['plan' => 'free', 'region' => 'us']),
            ]);
        });

        $this->assertDatabaseMissing('contact_attributes', ['client_id' => $client->id]);

        $this->artisan('fp:sync-contact-attributes')
            ->expectsOutputToContain('Syncing attributes for 2 contacts')
            ->expectsOutputToContain('Done')
            ->assertExitCode(0);

        $this->assertDatabaseHas('contact_attributes', ['client_id' => $client->id, 'name' => 'plan']);
        $this->assertDatabaseHas('contact_attributes', ['client_id' => $client->id, 'name' => 'company']);
        $this->assertDatabaseHas('contact_attributes', ['client_id' => $client->id, 'name' => 'region']);
    }
}
