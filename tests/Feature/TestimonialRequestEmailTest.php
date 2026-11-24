<?php

namespace Tests\Feature;

use App\Mail\TestimonialRequest;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialRequestEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_body_greets_the_contact_by_name(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id, 'name' => 'Ada Lovelace']);

        $html = (new TestimonialRequest($contact))->render();

        $this->assertStringContainsString('Hi Ada Lovelace,', $html);
        $this->assertStringNotContainsString('$contact', $html);
        $this->assertStringNotContainsString('{name}', $html);
    }

    public function test_the_greeting_uses_the_email_when_the_contact_has_no_name(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id, 'name' => '', 'email' => 'ada@example.com']);

        $html = (new TestimonialRequest($contact))->render();

        $this->assertStringContainsString('Hi ada@example.com,', $html);
    }

    public function test_a_body_saved_with_the_old_default_greeting_still_shows_the_name(): void
    {
        $client = Client::factory()->create();
        ClientSetting::query()
            ->where('client_id', $client->id)
            ->where('name', ClientSetting::NAME_TESTIMONIAL_EMAIL_BODY)
            ->update(['value' => "Hi {{ \$contact->name ?? \$contact->email }},\n\n{testimonialButton}\n\nThanks."]);
        $contact = Contact::factory()->create(['client_id' => $client->id, 'name' => 'Grace']);

        $html = (new TestimonialRequest($contact))->render();

        $this->assertStringContainsString('Hi Grace,', $html);
        $this->assertStringNotContainsString('$contact', $html);
    }

    public function test_the_settings_page_explains_the_name_placeholder(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/settings/testimonials')
            ->assertOk()
            ->assertSee('{name}')
            ->assertSee('Hi {name},');
    }
}
