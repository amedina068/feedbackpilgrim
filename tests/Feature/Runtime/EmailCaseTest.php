<?php

namespace Tests\Feature\Runtime;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MySQL compares emails without case (utf8mb4_unicode_ci). SQLite must do
 * the same, so that a login and a contact lookup work on both drivers.
 */
class EmailCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_email_matches_without_case(): void
    {
        $user = User::factory()->create(['email' => 'Ada@Example.com']);

        $this->assertTrue(User::where('email', 'ada@example.com')->first()?->is($user));
    }

    public function test_a_user_email_is_unique_without_case(): void
    {
        User::factory()->create(['email' => 'Ada@Example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['email' => 'ADA@example.com']);
    }

    public function test_a_contact_email_matches_without_case(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id, 'email' => 'Grace@Example.com']);

        $this->assertTrue(Contact::where('email', 'grace@example.com')->first()?->is($contact));
    }

    public function test_the_indexes_of_the_two_tables_stay(): void
    {
        // An installed package can add its own indexes, for example Laravel
        // Cashier adds users_stripe_id_index. The core indexes must stay.
        $this->assertContains('users_email_unique', $this->indexNames('users'));
        $this->assertContains('users_onboarding_index', $this->indexNames('users'));
        $this->assertContains('contacts_uuid_unique', $this->indexNames('contacts'));
        $this->assertContains('contacts_client_created', $this->indexNames('contacts'));
    }

    /**
     * @return list<string>
     */
    private function indexNames(string $table): array
    {
        return (new Collection(Schema::getIndexes($table)))
            ->reject(fn (array $index): bool => $index['primary'])
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }
}
