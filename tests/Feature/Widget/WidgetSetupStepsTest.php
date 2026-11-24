<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetSetupStepsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $this->user->id]);
    }

    public function test_the_page_shows_the_link_attributes_that_pilgrim_js_reads(): void
    {
        $pilgrim = file_get_contents(resource_path('js/pilgrim.js'));
        preg_match_all('/\[(data-pilgrim-[a-z-]+)\]/', $pilgrim, $matches);
        $attributes = array_unique($matches[1]);

        $this->assertNotEmpty($attributes);

        $response = $this->actingAs($this->user)->get('/settings/widget')->assertOk();

        foreach ($attributes as $attribute) {
            $response->assertSee($attribute);
        }
    }

    public function test_the_init_snippet_shows_the_revenue_fields_and_the_options(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/widget')
            ->assertOk()
            ->assertSee('mrr: 49')
            ->assertSee('ltv: 1200')
            ->assertSee('releaseNotes: { show: true, open: true }')
            ->assertSee('testimonials: true');
    }

    public function test_the_keys_show_in_full_with_named_copy_buttons(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/widget')
            ->assertOk()
            ->assertSee('label="Copy key"', false)
            ->assertSee('label="Copy secret"', false)
            ->assertDontSee('<label for="identity_secret"', false);
    }
}
