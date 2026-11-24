<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Testimonial;
use App\Support\WidgetContactToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Js;
use Tests\TestCase;

/**
 * The testimonial widget posts to /api/v1/testimonials. The contact comes
 * only from a token that the server signed in `init`. A guest cannot choose
 * a contact and cannot read contact data from the response.
 */
class TestimonialSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function url(Client $client, string $path): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').$path;
    }

    public function test_a_guest_cannot_choose_the_contact_with_contact_id(): void
    {
        $client = Client::factory()->create();
        $victim = Contact::factory()->create(['client_id' => $client->id, 'name' => null, 'email' => 'vip@customer.test']);

        $this->postJson($this->url($client, '/api/v1/testimonials'), [
            'description' => 'Great product.',
            'contact_id' => $victim->id,
        ])->assertCreated()
            ->assertExactJson(['message' => 'Testimonial created successfully']);

        $testimonial = Testimonial::query()->sole();
        $this->assertNull($testimonial->contact_id);
        $this->assertSame('Anonymous User', $testimonial->username);
    }

    public function test_a_signed_contact_token_links_the_testimonial_to_the_contact(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id, 'name' => 'Jane']);

        $this->postJson($this->url($client, '/api/v1/testimonials'), [
            'description' => 'Great product.',
            'contact_token' => WidgetContactToken::issue($contact),
        ])->assertCreated()
            ->assertExactJson(['message' => 'Testimonial created successfully']);

        $testimonial = Testimonial::query()->sole();
        $this->assertSame($contact->id, $testimonial->contact_id);
        $this->assertSame('Jane', $testimonial->username);
    }

    public function test_a_token_of_another_client_gives_an_anonymous_testimonial(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherContact = Contact::factory()->create(['client_id' => $otherClient->id]);

        $this->postJson($this->url($client, '/api/v1/testimonials'), [
            'description' => 'Great product.',
            'contact_token' => WidgetContactToken::issue($otherContact),
        ])->assertCreated();

        $this->assertNull(Testimonial::query()->sole()->contact_id);
    }

    public function test_an_expired_or_forged_token_gives_an_anonymous_testimonial(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $expired = WidgetContactToken::issue($contact, now()->subMinute());

        foreach ([$expired, 'forged-value'] as $token) {
            $this->postJson($this->url($client, '/api/v1/testimonials'), [
                'description' => 'Great product.',
                'contact_token' => $token,
            ])->assertCreated();
        }

        $this->assertSame(0, Testimonial::query()->whereNotNull('contact_id')->count());
    }

    public function test_testimonial_submissions_are_throttled(): void
    {
        $client = Client::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson($this->url($client, '/api/v1/testimonials'), ['description' => 'Spam '.$attempt])
                ->assertCreated();
        }

        $this->postJson($this->url($client, '/api/v1/testimonials'), ['description' => 'Spam 6'])
            ->assertTooManyRequests();
    }

    public function test_init_gives_a_testimonial_url_with_a_contact_token_and_no_contact_id(): void
    {
        Redis::shouldReceive('get')->andReturn(null);
        Redis::shouldReceive('set')->andReturn(true);
        $client = Client::factory()->create(['require_identity_verification' => false]);

        $url = $this->withToken($client->publishable_key)->postJson('/api/v1/init', [
            'contact' => ['email' => 'jane@example.com'],
            'testimonials' => true,
        ])->assertOk()->json('testimonials.url');

        $this->assertStringNotContainsString('contact_id=', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $contact = Contact::query()->where('email', 'jane@example.com')->sole();
        $this->assertTrue($contact->is(WidgetContactToken::resolve($query['contact_token'], $client)));
    }

    public function test_the_iframe_passes_only_a_valid_token_to_the_widget(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $token = WidgetContactToken::issue($contact);

        $this->get($this->url($client, '/iframe/testimonials?contact_token='.urlencode($token)))
            ->assertOk()
            ->assertSee(':contact-token="'.Js::from($token)->toHtml().'"', false);

        $this->get($this->url($client, '/iframe/testimonials?contact_token=forged'))
            ->assertOk()
            ->assertSee(':contact-token="null"', false)
            ->assertDontSee('forged');
    }
}
