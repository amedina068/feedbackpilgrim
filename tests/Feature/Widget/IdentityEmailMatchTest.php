<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production uses MySQL with a utf8mb4 collation that ignores case and
 * accents. Thus "jose@" and "josé@" are equal in a WHERE clause. These tests
 * give SQLite the same comparison, so that they show the production result.
 * On MySQL, the real collation does the comparison.
 */
class IdentityEmailMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $fold = fn (string $value): string => strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $value));

        // The email columns use NOCASE on SQLite. The other text columns use BINARY.
        foreach (['BINARY', 'NOCASE'] as $collation) {
            DB::connection()->getPdo()->sqliteCreateCollation(
                $collation,
                fn (string $left, string $right): int => strcmp($fold($left), $fold($right)),
            );
        }
    }

    private function initAs(Client $client, string $email, string $name)
    {
        return $this->withToken($client->publishable_key)->postJson('/api/v1/init', [
            'contact' => ['email' => $email, 'name' => $name],
            'user_hash' => $client->identityHashFor($email),
        ]);
    }

    public function test_the_collation_emulation_matches_an_accent_variant(): void
    {
        $client = Client::factory()->create();
        Contact::factory()->create(['client_id' => $client->id, 'email' => 'josé@example.com']);

        $this->assertSame(1, Contact::query()->where('email', 'jose@example.com')->count());
    }

    public function test_a_verified_email_does_not_match_a_contact_with_an_accent_variant(): void
    {
        $client = Client::factory()->create(['require_identity_verification' => true]);
        $victim = Contact::factory()->create([
            'client_id' => $client->id,
            'email' => 'josé@example.com',
            'name' => 'José',
        ]);

        $response = $this->initAs($client, 'jose@example.com', 'Attacker')->assertOk();

        $this->assertSame('José', $victim->fresh()->name);
        $this->assertStringNotContainsString($victim->uuid, $response->json('auth_url'));
        $this->assertSame(2, Contact::query()->where('client_id', $client->id)->count());
    }

    public function test_a_verified_email_still_matches_the_same_contact_in_another_case(): void
    {
        $client = Client::factory()->create(['require_identity_verification' => true]);
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'email' => 'Jane@Example.com',
            'name' => 'Jane',
        ]);

        $response = $this->initAs($client, ' jane@example.com ', 'Jane Doe')->assertOk();

        $this->assertSame('Jane Doe', $contact->fresh()->name);
        $this->assertStringContainsString($contact->uuid, $response->json('auth_url'));
        $this->assertSame(1, Contact::query()->where('client_id', $client->id)->count());
    }
}
