<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The embed widget (pilgrim.js) calls POST /api/v1/init with the client's
 * publishable key. The key identifies the client. It is not a credential.
 */
class WidgetInitTest extends TestCase
{
    use RefreshDatabase;

    private function client(bool $requireIdentityVerification = false): Client
    {
        return Client::factory()->create([
            'require_identity_verification' => $requireIdentityVerification,
        ]);
    }

    private function init(?string $key, array $payload = [])
    {
        $request = $key === null ? $this : $this->withToken($key);

        return $request->postJson('/api/v1/init', array_merge([
            'contact' => ['email' => 'jane@example.com', 'name' => 'Jane'],
        ], $payload));
    }

    public function test_init_without_contact_is_a_validation_error(): void
    {
        $client = $this->client();

        $this->withToken($client->publishable_key)
            ->postJson('/api/v1/init', ['releaseNotes' => ['show' => true]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact');

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_init_without_contact_email_is_a_validation_error(): void
    {
        $client = $this->client();

        $this->init($client->publishable_key, ['contact' => ['name' => 'Jane']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact.email');
    }

    public function test_init_with_nested_contact_without_email_is_a_validation_error(): void
    {
        $client = $this->client();

        $this->init($client->publishable_key, ['contact' => ['contact' => ['name' => 'Jane']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact.contact.email');
    }

    public function test_init_without_contact_returns_json_when_the_request_does_not_ask_for_json(): void
    {
        $client = $this->client();

        $this->withToken($client->publishable_key)
            ->post('/api/v1/init', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact');
    }

    public function test_new_client_gets_a_publishable_key(): void
    {
        $client = $this->client();

        $this->assertMatchesRegularExpression('/^pk_[A-Za-z0-9]{32}$/', $client->publishable_key);
    }

    public function test_init_with_publishable_key_returns_auth_url(): void
    {
        $client = $this->client();

        $this->init($client->publishable_key)
            ->assertOk()
            ->assertJsonStructure(['auth_url']);

        $this->assertDatabaseHas('contacts', [
            'client_id' => $client->id,
            'email' => 'jane@example.com',
        ]);
    }

    public function test_init_with_unknown_publishable_key_is_rejected(): void
    {
        $this->client();

        $this->init('pk_'.str_repeat('a', 32))
            ->assertStatus(401)
            ->assertJsonMissingPath('auth_url');

        $this->assertSame(0, Contact::count());
    }

    public function test_init_with_unknown_legacy_value_is_rejected(): void
    {
        $this->client();

        $this->init('1|not-a-known-legacy-key')->assertStatus(401);
    }

    public function test_init_without_key_is_rejected(): void
    {
        $this->client();

        $this->init(null)->assertStatus(401);
    }

    public function test_init_does_not_accept_a_private_sanctum_token(): void
    {
        $client = $this->client();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        $token = $user->createToken('API Key', ['*']);
        $token->accessToken->forceFill(['client_id' => $client->id])->save();

        $this->init($token->plainTextToken)->assertStatus(401);
    }

    /**
     * Old public Sanctum tokens are in the git history. The widget accepts
     * only the publishable key, so an old token value gets 401.
     */
    public function test_init_rejects_an_old_public_token_value(): void
    {
        $this->client();

        $this->init('7|'.str_repeat('a', 40))->assertStatus(401);

        $this->assertSame(0, Contact::count());
    }

    public function test_init_with_valid_user_hash_works_when_verification_is_required(): void
    {
        $client = $this->client(requireIdentityVerification: true);
        $hash = hash_hmac('sha256', 'jane@example.com', $client->identity_secret);

        $this->init($client->publishable_key, ['user_hash' => $hash])
            ->assertOk()
            ->assertJsonStructure(['auth_url']);
    }

    public function test_user_hash_uses_the_trimmed_lowercase_email(): void
    {
        $client = $this->client(requireIdentityVerification: true);
        $hash = hash_hmac('sha256', 'jane@example.com', $client->identity_secret);

        $this->init($client->publishable_key, [
            'contact' => ['email' => '  Jane@Example.com ', 'name' => 'Jane'],
            'user_hash' => $hash,
        ])->assertOk();
    }

    public function test_user_hash_works_with_the_nested_contact_payload(): void
    {
        $client = $this->client(requireIdentityVerification: true);
        $hash = hash_hmac('sha256', 'jane@example.com', $client->identity_secret);

        $this->init($client->publishable_key, [
            'contact' => ['contact' => ['email' => 'jane@example.com']],
            'user_hash' => $hash,
        ])->assertOk();
    }

    public function test_wrong_user_hash_is_rejected_when_verification_is_required(): void
    {
        $client = $this->client(requireIdentityVerification: true);

        $this->init($client->publishable_key, ['user_hash' => str_repeat('0', 64)])
            ->assertStatus(403)
            ->assertJsonMissingPath('auth_url');

        $this->assertSame(0, Contact::count());
    }

    public function test_wrong_user_hash_is_rejected_when_verification_is_optional(): void
    {
        $client = $this->client(requireIdentityVerification: false);

        $this->init($client->publishable_key, ['user_hash' => str_repeat('0', 64)])
            ->assertStatus(403)
            ->assertJsonMissingPath('auth_url');
    }

    public function test_hash_for_another_email_is_rejected(): void
    {
        $client = $this->client(requireIdentityVerification: false);
        $hashForOtherUser = hash_hmac('sha256', 'attacker@example.com', $client->identity_secret);

        $this->init($client->publishable_key, ['user_hash' => $hashForOtherUser])
            ->assertStatus(403);
    }

    public function test_hash_made_with_another_clients_secret_is_rejected(): void
    {
        $client = $this->client(requireIdentityVerification: true);
        $otherClient = $this->client();
        $hash = hash_hmac('sha256', 'jane@example.com', $otherClient->identity_secret);

        $this->init($client->publishable_key, ['user_hash' => $hash])->assertStatus(403);
    }

    public function test_hash_made_with_an_empty_key_is_rejected_when_the_client_has_no_secret(): void
    {
        $client = $this->client(requireIdentityVerification: false);
        $client->forceFill(['identity_secret' => null])->save();
        $hash = hash_hmac('sha256', 'jane@example.com', '');

        $this->init($client->publishable_key, ['user_hash' => $hash])->assertStatus(403);
    }

    public function test_non_string_user_hash_is_rejected(): void
    {
        $client = $this->client(requireIdentityVerification: false);

        $this->init($client->publishable_key, ['user_hash' => ['x']])->assertStatus(403);
    }

    public function test_missing_user_hash_is_rejected_when_verification_is_required(): void
    {
        $client = $this->client(requireIdentityVerification: true);

        $this->init($client->publishable_key)
            ->assertStatus(403)
            ->assertJsonMissingPath('auth_url');

        $this->assertSame(0, Contact::count());
    }

    public function test_missing_user_hash_is_accepted_when_verification_is_optional(): void
    {
        $client = $this->client(requireIdentityVerification: false);

        $this->init($client->publishable_key)
            ->assertOk()
            ->assertJsonStructure(['auth_url']);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function payloadsWithoutContactEmail(): array
    {
        return [
            'null contact' => [null],
            'string contact' => ['jane@example.com'],
            'contact without email' => [['name' => 'Jane']],
            'blank email' => [['email' => '   ']],
            'non-string email' => [['email' => ['jane@example.com']]],
            'null nested contact' => [['contact' => null]],
            'nested contact without email' => [['contact' => ['name' => 'Jane']]],
        ];
    }

    #[DataProvider('payloadsWithoutContactEmail')]
    public function test_init_without_a_contact_email_returns_422(mixed $contact): void
    {
        $client = $this->client();

        $this->init($client->publishable_key, ['contact' => $contact])
            ->assertStatus(422)
            ->assertJsonMissingPath('auth_url');

        $this->assertSame(0, Contact::count());
    }

    public function test_init_without_a_contact_key_returns_422(): void
    {
        $client = $this->client();

        $this->withToken($client->publishable_key)
            ->postJson('/api/v1/init', ['releaseNotes' => ['show' => true]])
            ->assertStatus(422);

        $this->assertSame(0, Contact::count());
    }

    public function test_without_a_user_hash_an_existing_contact_gets_no_sign_in_link_and_no_update(): void
    {
        $client = $this->client(requireIdentityVerification: false);
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'mrr' => 10,
            'metadata' => json_encode(['plan' => 'pro']),
        ]);

        $response = $this->init($client->publishable_key, [
            'contact' => ['email' => 'jane@example.com', 'name' => 'Renamed', 'mrr' => 999999],
            'testimonials' => true,
        ])->assertOk();

        $this->assertSame($client->public_site.'/posts', $response->json('auth_url'));
        $this->assertStringNotContainsString($contact->uuid, $response->getContent());
        $this->assertStringNotContainsString('contact_token', (string) $response->json('testimonials.url'));

        $contact->refresh();
        $this->assertSame('Jane', $contact->name);
        $this->assertEquals(10, $contact->mrr);
        $this->assertSame(['plan' => 'pro'], json_decode($contact->metadata, true));
    }

    public function test_without_a_user_hash_the_admin_contact_gets_no_sign_in_link(): void
    {
        $client = $this->client(requireIdentityVerification: false);
        $admin = User::factory()->create();
        $client->users()->attach($admin);
        $adminContact = $admin->adminContactFor($client);

        $response = $this->init($client->publishable_key, [
            'contact' => ['email' => $admin->email, 'name' => 'Renamed'],
        ])->assertOk();

        $this->assertStringNotContainsString($adminContact->uuid, $response->getContent());
        $this->assertNotSame('Renamed', $adminContact->fresh()->name);

        $this->get($response->json('auth_url'));
        $this->assertGuest('contact');
    }

    public function test_a_verified_user_hash_never_gives_a_sign_in_link_for_the_admin_contact(): void
    {
        $client = $this->client(requireIdentityVerification: true);
        $admin = User::factory()->create();
        $client->users()->attach($admin);
        $adminContact = $admin->adminContactFor($client);

        $response = $this->init($client->publishable_key, [
            'contact' => ['email' => $admin->email],
            'user_hash' => $client->identityHashFor($admin->email),
        ])->assertOk();

        $this->assertStringNotContainsString($adminContact->uuid, $response->getContent());
        $this->assertSame($client->public_site.'/posts', $response->json('auth_url'));
    }

    public function test_without_a_user_hash_a_new_contact_gets_a_sign_in_link(): void
    {
        $client = $this->client(requireIdentityVerification: false);

        $response = $this->init($client->publishable_key)->assertOk();

        $contact = Contact::query()->where('email', 'jane@example.com')->sole();
        $this->assertStringContainsString('/authenticate/'.$contact->uuid, $response->json('auth_url'));
    }

    public function test_a_verified_user_hash_gives_an_existing_contact_a_sign_in_link_and_updates_it(): void
    {
        $client = $this->client(requireIdentityVerification: true);
        $contact = Contact::factory()->create(['client_id' => $client->id, 'email' => 'jane@example.com', 'name' => 'Jane']);

        $response = $this->init($client->publishable_key, [
            'contact' => ['email' => 'jane@example.com', 'name' => 'Jane Doe'],
            'user_hash' => $client->identityHashFor('jane@example.com'),
            'testimonials' => true,
        ])->assertOk();

        $this->assertStringContainsString('/authenticate/'.$contact->uuid, $response->json('auth_url'));
        $this->assertStringContainsString('contact_token=', $response->json('testimonials.url'));
        $this->assertSame('Jane Doe', $contact->fresh()->name);
    }

    public function test_a_verified_contact_gets_the_newest_release_note_opened_one_time(): void
    {
        $client = $this->client();
        Contact::factory()->create(['client_id' => $client->id, 'email' => 'jane@example.com']);
        ReleaseNote::factory()->create(['client_id' => $client->id, 'published_at' => now()]);

        $payload = [
            'user_hash' => $client->identityHashFor('jane@example.com'),
            'releaseNotes' => ['show' => true, 'open' => true],
        ];

        $this->init($client->publishable_key, $payload)->assertOk()->assertJsonPath('releaseNotes.open', true);
        $this->init($client->publishable_key, $payload)->assertOk()->assertJsonPath('releaseNotes.open', false);
    }

    public function test_release_notes_without_show_do_not_fail(): void
    {
        $client = $this->client();

        $this->init($client->publishable_key, ['releaseNotes' => ['open' => true]])
            ->assertOk()
            ->assertJsonMissingPath('releaseNotes.show');
    }

    public function test_without_a_user_hash_an_existing_contact_does_not_get_the_release_notes_opened(): void
    {
        $client = $this->client(requireIdentityVerification: false);
        Contact::factory()->create(['client_id' => $client->id, 'email' => 'jane@example.com']);
        ReleaseNote::factory()->create(['client_id' => $client->id, 'published_at' => now()]);

        $this->init($client->publishable_key, ['releaseNotes' => ['show' => true, 'open' => true]])
            ->assertOk()
            ->assertJsonPath('releaseNotes.show', true)
            ->assertJsonPath('releaseNotes.open', false);
    }
}
