<?php

namespace Tests\Unit\Support;

use App\Models\Client;
use App\Models\Contact;
use App\Support\ContactPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_metadata_excludes_known_fields_and_merges_explicit_metadata(): void
    {
        $input = [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'mrr' => 10,
            'ltv' => 99,
            'avatar' => 'https://x/y.png',
            'tags' => [1, 2],
            'plan' => 'enterprise',
            'metadata' => ['tier' => 'gold'],
        ];

        $meta = ContactPresenter::extractMetadata($input);

        $this->assertSame('enterprise', $meta['plan']);
        $this->assertSame('gold', $meta['tier']);
        $this->assertArrayNotHasKey('name', $meta);
        $this->assertArrayNotHasKey('email', $meta);
        $this->assertArrayNotHasKey('tags', $meta);
        $this->assertArrayNotHasKey('metadata', $meta);
    }

    public function test_present_strips_raw_metadata_and_flattens_custom_fields(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode(['plan' => 'enterprise', 'tier' => 'gold']),
        ]);

        $result = ContactPresenter::present($contact->fresh());

        $this->assertArrayNotHasKey('metadata', $result);
        $this->assertSame('enterprise', $result['plan']);
        $this->assertSame('gold', $result['tier']);
        $this->assertSame($contact->id, $result['id']);
    }

    public function test_encode_metadata_preserves_pilgrim_fields_on_existing_contact(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create([
            'client_id' => $client->id,
            'metadata' => json_encode([
                'plan' => 'basic',
                'pilgrim_last_login' => '2024-01-01',
            ]),
        ]);

        $encoded = ContactPresenter::encodeMetadata(['plan' => 'pro'], $contact->fresh());
        $decoded = json_decode($encoded, true);

        $this->assertSame('pro', $decoded['plan']);
        $this->assertSame('2024-01-01', $decoded['pilgrim_last_login']);
    }
}
