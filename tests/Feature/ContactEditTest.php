<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactEditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);

        $this->contact = Contact::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
    }

    public function test_the_edit_page_opens(): void
    {
        $this->actingAs($this->user)
            ->get("/contacts/{$this->contact->id}/edit")
            ->assertOk()
            ->assertSee('ada@example.com');
    }

    public function test_the_edit_page_labels_each_field(): void
    {
        $this->actingAs($this->user)
            ->get("/contacts/{$this->contact->id}/edit")
            ->assertOk()
            ->assertSee('<label for="name"', false)
            ->assertSee('<label for="email"', false)
            ->assertSee('<label for="ltv"', false)
            ->assertSee('<label for="mrr"', false);
    }

    public function test_the_edit_page_checks_the_tags_of_the_contact(): void
    {
        $attached = Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'vip']);
        Tag::factory()->create(['client_id' => $this->client->id, 'title' => 'churned']);
        $this->contact->tags()->attach($attached);

        $html = $this->actingAs($this->user)
            ->get("/contacts/{$this->contact->id}/edit")
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="tags-'.$attached->id.'"[^>]*checked/', $html);
        $this->assertSame(1, preg_match_all('/name="tags\[\]"[^>]*checked/', $html));
    }

    public function test_a_save_without_changes_keeps_the_tags(): void
    {
        $tag = Tag::factory()->create(['client_id' => $this->client->id]);
        $this->contact->tags()->attach($tag);

        $this->actingAs($this->user)
            ->patch("/contacts/{$this->contact->id}", [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'tags' => [$tag->id],
            ])
            ->assertRedirect("/contacts/{$this->contact->id}");

        $this->assertTrue($this->contact->fresh()->tags->contains($tag));
    }

    public function test_an_invalid_email_and_a_negative_mrr_show_errors(): void
    {
        $this->actingAs($this->user)
            ->from("/contacts/{$this->contact->id}/edit")
            ->patch("/contacts/{$this->contact->id}", [
                'email' => 'not-an-email',
                'mrr' => '-5',
            ])
            ->assertRedirect("/contacts/{$this->contact->id}/edit")
            ->assertSessionHasErrors(['email', 'mrr']);

        $this->assertSame('ada@example.com', $this->contact->fresh()->email);
    }

    public function test_a_save_keeps_only_the_custom_fields_that_the_form_sends(): void
    {
        $this->contact->update(['metadata' => json_encode([
            'company' => 'Acme',
            'pilgrim_last_login' => '2026-11-01 10:00:00',
        ])]);

        $this->actingAs($this->user)
            ->patch("/contacts/{$this->contact->id}", [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'custom_fields' => ['plan' => 'pro'],
            ])
            ->assertRedirect("/contacts/{$this->contact->id}");

        $this->assertSame(
            ['plan' => 'pro', 'pilgrim_last_login' => '2026-11-01 10:00:00'],
            json_decode($this->contact->fresh()->metadata, true),
        );
    }

    public function test_a_save_without_custom_fields_keeps_only_the_pilgrim_fields(): void
    {
        $this->contact->update(['metadata' => json_encode([
            'company' => 'Acme',
            'pilgrim_last_login' => '2026-11-01 10:00:00',
        ])]);

        $this->actingAs($this->user)
            ->patch("/contacts/{$this->contact->id}", [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ])
            ->assertRedirect("/contacts/{$this->contact->id}");

        $this->assertSame(
            ['pilgrim_last_login' => '2026-11-01 10:00:00'],
            json_decode($this->contact->fresh()->metadata, true),
        );
    }
}
