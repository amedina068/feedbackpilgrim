<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests: lock current public-API behavior before the MCP refactor
 * extracts shared Actions/Rules. These document behavior the refactor must preserve
 * (a–c). Case (d) documents the current raw sort_direction handling and is intentionally
 * flipped by T-105 (clamping); it is updated there.
 */
class CharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create();
        $this->user->clients()->attach($this->client);
    }

    protected function authenticate(): void
    {
        $token = $this->user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $this->client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);
    }

    public function test_contact_presentation_strips_raw_metadata_key_and_flattens_custom_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/contacts', [
            'name' => 'Meta Contact',
            'email' => 'meta@example.com',
            'plan' => 'enterprise',
            'tier' => 'gold',
        ]);

        $response->assertStatus(201);

        $body = $response->json();
        $this->assertArrayNotHasKey('metadata', $body, 'raw metadata key must not be exposed');
        $this->assertSame('enterprise', $body['plan']);
        $this->assertSame('gold', $body['tier']);
    }

    public function test_release_note_store_generates_date_suffixed_slug_forces_draft_and_records_author(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/public/v1/release-notes', [
            'title' => 'Big Launch',
            'content' => 'Lots of new things.',
        ]);

        $response->assertStatus(201);

        $note = ReleaseNote::firstOrFail();
        $this->assertSame('big-launch-'.now()->format('Ymd'), $note->slug);
        $this->assertNull($note->published_at);
        $this->assertSame($this->user->id, $note->user_id);
    }

    public function test_testimonial_index_returns_only_pending_when_approved_param_absent(): void
    {
        $this->authenticate();

        $approved = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => now(),
        ]);
        $pending = Testimonial::factory()->create([
            'client_id' => $this->client->id,
            'approved_at' => null,
        ]);

        $response = $this->getJson('/api/public/v1/testimonials');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($approved->id, $ids, 'index defaults to pending-only (documented parity bug)');
    }

    public function test_invalid_sort_direction_is_clamped_to_a_safe_default(): void
    {
        $this->authenticate();

        Contact::factory()->count(2)->create(['client_id' => $this->client->id]);

        $response = $this->getJson('/api/public/v1/contacts?sort_by=name&sort_direction=sideways');

        // T-105: invalid direction is clamped, never reaching orderBy raw (was a 500).
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
