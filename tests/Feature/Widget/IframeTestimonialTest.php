<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The testimonial iframe puts a query value into a Vue prop binding. Vue runs
 * a binding as JavaScript, so no raw query value can reach the binding.
 */
class IframeTestimonialTest extends TestCase
{
    use RefreshDatabase;

    private function iframeUrl(Client $client, string $query = ''): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').'/iframe/testimonials'.$query;
    }

    public function test_a_script_in_the_query_does_not_reach_the_vue_binding(): void
    {
        $client = Client::factory()->create();
        $payload = rawurlencode('$emit.constructor(`alert(document.domain)`)()');

        $this->get($this->iframeUrl($client, '?contact_id='.$payload.'&contact_token='.$payload))
            ->assertOk()
            ->assertDontSee('$emit.constructor', false)
            ->assertSee(':contact-token="null"', false);
    }

    public function test_no_query_gives_null(): void
    {
        $client = Client::factory()->create();

        $this->get($this->iframeUrl($client))
            ->assertOk()
            ->assertSee(':contact-token="null"', false);
    }
}
