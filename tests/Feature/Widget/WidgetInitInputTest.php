<?php

namespace Tests\Feature\Widget;

use App\Http\Controllers\Api\InitController;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The publishable key is public. Anyone can call widget `init` with it, so
 * the call checks the contact fields and limits new unverified contacts.
 */
class WidgetInitInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('set')->andReturn(true);
    }

    private function client(): Client
    {
        return Client::factory()->create(['require_identity_verification' => false]);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function init(Client $client, array $contact, array $payload = [], string $ip = '203.0.113.10')
    {
        return $this->withToken($client->publishable_key)
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/init', array_merge(['contact' => $contact], $payload));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidContacts(): array
    {
        return [
            'not an email' => [['email' => 'not-an-email'], 'contact.email'],
            'long email' => [['email' => str_repeat('a', 250).'@example.com'], 'contact.email'],
            'long name' => [['email' => 'jane@example.com', 'name' => str_repeat('a', 256)], 'contact.name'],
            'avatar is not a URL' => [['email' => 'jane@example.com', 'avatar' => 'not a url'], 'contact.avatar'],
            'avatar is not http' => [['email' => 'jane@example.com', 'avatar' => 'ftp://example.com/a.png'], 'contact.avatar'],
            'mrr is not a number' => [['email' => 'jane@example.com', 'mrr' => 'lots'], 'contact.mrr'],
            'negative ltv' => [['email' => 'jane@example.com', 'ltv' => -5], 'contact.ltv'],
            'nested email is not an email' => [['contact' => ['email' => 'nope']], 'contact.contact.email'],
            'nested long name' => [['contact' => ['email' => 'jane@example.com', 'name' => str_repeat('a', 256)]], 'contact.contact.name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    #[DataProvider('invalidContacts')]
    public function test_init_rejects_an_invalid_contact_field(array $contact, string $field): void
    {
        $client = $this->client();

        $this->init($client, $contact)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_init_rejects_too_much_custom_contact_data(): void
    {
        $client = $this->client();

        $contact = ['email' => 'jane@example.com', 'notes' => str_repeat('x', 20000)];

        $this->init($client, $contact)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact');

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_init_rejects_too_many_custom_contact_fields(): void
    {
        $client = $this->client();

        $contact = ['email' => 'jane@example.com'];

        foreach (range(1, 60) as $number) {
            $contact["field_{$number}"] = 'value';
        }

        $this->init($client, $contact)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact');
    }

    public function test_init_rejects_a_reserved_custom_field(): void
    {
        $client = $this->client();

        $this->init($client, ['email' => 'jane@example.com', 'pilgrim_last_release_note' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact');
    }

    public function test_init_accepts_a_normal_contact_with_custom_fields(): void
    {
        $client = $this->client();

        $this->init($client, [
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'avatar' => 'https://example.com/jane.png',
            'mrr' => '99.50',
            'ltv' => 1200,
            'plan' => 'premium',
        ])->assertOk();

        $contact = Contact::query()->where('client_id', $client->id)->sole();
        $this->assertSame('premium', $contact->metadataArray['plan']);
    }

    public function test_one_address_can_create_only_a_limited_number_of_unverified_contacts(): void
    {
        $client = $this->client();
        $limit = InitController::NEW_UNVERIFIED_CONTACTS_PER_HOUR;

        foreach (range(1, $limit) as $number) {
            $this->init($client, ['email' => "person{$number}@example.com"])->assertOk();
        }

        $this->init($client, ['email' => 'one-more@example.com'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('contacts', $limit);

        $this->init($client, ['email' => 'other-address@example.com'], [], '198.51.100.20')->assertOk();
    }

    public function test_the_limit_does_not_block_an_existing_contact(): void
    {
        $client = $this->client();
        $limit = InitController::NEW_UNVERIFIED_CONTACTS_PER_HOUR;

        foreach (range(1, $limit) as $number) {
            $this->init($client, ['email' => "person{$number}@example.com"])->assertOk();
        }

        $this->init($client, ['email' => 'person1@example.com'])->assertOk();
    }

    public function test_the_limit_does_not_count_verified_contacts(): void
    {
        $client = $this->client();
        $limit = InitController::NEW_UNVERIFIED_CONTACTS_PER_HOUR;

        foreach (range(1, $limit + 2) as $number) {
            $email = "person{$number}@example.com";

            $this->init($client, ['email' => $email], ['user_hash' => $client->identityHashFor($email)])->assertOk();
        }

        $this->assertDatabaseCount('contacts', $limit + 2);
    }
}
