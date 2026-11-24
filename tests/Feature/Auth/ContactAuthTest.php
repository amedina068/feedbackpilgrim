<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The client-facing site authenticates Contacts on the `contact` guard via a
 * magic-link route, separate from the admin `web` guard. These pin that the
 * `auth:contact` guard and `identifyClient` middleware stay wired.
 */
class ContactAuthTest extends TestCase
{
    use RefreshDatabase;

    private function subdomainUrl(Client $client, string $path): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').$path;
    }

    public function test_magic_link_authenticates_contact_guard(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $response = $this->get($contact->generateAuthUrl());

        $response->assertRedirect('/posts');
        $this->assertAuthenticatedAs($contact, 'contact');
    }

    public function test_magic_link_redirects_to_a_relative_path(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $this->get($contact->generateAuthUrl('/testimonials/create'))
            ->assertRedirect('/testimonials/create');
        $this->assertAuthenticatedAs($contact, 'contact');
    }

    public function test_unknown_contact_uuid_is_rejected(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $url = str_replace($contact->uuid, '00000000-0000-0000-0000-000000000000', $contact->generateAuthUrl());

        $this->get($url)->assertRedirect('/posts');
        $this->assertGuest('contact');
    }

    public function test_an_unsigned_link_does_not_sign_in(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $this->get($this->subdomainUrl($client, '/authenticate/'.$contact->uuid.'?redirect_url=https://evil.example/login'))
            ->assertRedirect('/posts');
        $this->assertGuest('contact');
    }

    public function test_a_link_without_an_expiry_does_not_sign_in(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $path = URL::signedRoute('authenticate-contact', ['contactUuid' => $contact->uuid], absolute: false);

        $this->get($client->public_site.$path)->assertRedirect('/posts');
        $this->assertGuest('contact');
    }

    public function test_a_changed_redirect_breaks_the_signature(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $url = $contact->generateAuthUrl().'&redirect_url='.urlencode('https://evil.example/login');

        $this->get($url)->assertRedirect('/posts');
        $this->assertGuest('contact');
    }

    public function test_a_signed_absolute_redirect_goes_to_the_board(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        foreach (['https://evil.example/login', '//evil.example/login', '/\\evil.example/login'] as $redirect) {
            $this->get($contact->generateAuthUrl($redirect))->assertRedirect('/posts');
        }
    }

    public function test_an_expired_link_does_not_sign_in(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $url = $contact->generateAuthUrl();

        $this->travel(Contact::AUTH_LINK_LIFETIME_HOURS + 1)->hours();

        $this->get($url)->assertRedirect('/posts');
        $this->assertGuest('contact');
    }

    public function test_an_email_link_lasts_longer_than_a_page_view_link(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $url = $contact->generateEmailAuthUrl();

        $this->travel(Contact::EMAIL_AUTH_LINK_LIFETIME_DAYS - 1)->days();

        $this->get($url)->assertRedirect('/posts');
        $this->assertAuthenticatedAs($contact, 'contact');
    }

    public function test_a_link_for_a_contact_of_another_client_does_not_sign_in(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherContact = Contact::factory()->create(['client_id' => $otherClient->id]);
        $path = (string) parse_url($otherContact->generateAuthUrl(), PHP_URL_PATH).'?'.parse_url($otherContact->generateAuthUrl(), PHP_URL_QUERY);

        $this->get($this->subdomainUrl($client, $path))->assertNotFound();
        $this->assertGuest('contact');
    }

    public function test_guest_cannot_post_to_contact_guarded_route(): void
    {
        $client = Client::factory()->create();

        $response = $this->post($this->subdomainUrl($client, '/posts'), [
            'title' => 'A feature idea',
            'content' => 'Please build this.',
        ]);

        $response->assertRedirect();
        $this->assertGuest('contact');
    }
}
