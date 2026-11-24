<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DashboardFormErrorsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($this->client);
        Contact::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->user->id]);
    }

    public function test_a_release_note_without_a_title_keeps_the_content(): void
    {
        $this->actingAs($this->user)
            ->from('/release-notes/create')
            ->followingRedirects()
            ->post('/release-notes', ['title' => '', 'content' => 'A long body that took ten minutes'])
            ->assertOk()
            ->assertSee('A long body that took ten minutes');
    }

    public function test_a_release_note_without_content_shows_the_error(): void
    {
        $this->actingAs($this->user)
            ->from('/release-notes/create')
            ->followingRedirects()
            ->post('/release-notes', ['title' => 'Version 2', 'content' => ''])
            ->assertOk()
            ->assertSee('The content field is required.');
    }

    public function test_the_comment_forms_show_the_content_error(): void
    {
        $post = Post::factory()->create(['client_id' => $this->client->id]);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['content' => ['The content field is required.']]));

        foreach (["/posts/{$post->id}", "/posts/{$post->id}/comments"] as $page) {
            $this->actingAs($this->user)
                ->withSession(['errors' => $errors, '_old_input' => ['content' => 'Half a thought']])
                ->get($page)
                ->assertOk()
                ->assertSee('The content field is required.')
                ->assertSee('Half a thought');
        }
    }

    public function test_the_onboarding_page_has_its_own_title_and_keeps_the_subdomain(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        $this->actingAs($user)
            ->from('/onboarding/project')
            ->followingRedirects()
            ->post('/onboarding/project', ['name' => 'Acme', 'subdomain' => 'app'])
            ->assertOk()
            ->assertSee('Create your project')
            ->assertDontSee('Sign in to your account')
            ->assertSee('value="app"', false)
            ->assertSee('This subdomain is reserved.')
            ->assertSee('Create project');
    }

    public function test_the_register_page_says_create_an_account(): void
    {
        Registration::setMode(Registration::OPEN);

        $this->get('/register')
            ->assertOk()
            ->assertSee('Create an account')
            ->assertDontSee('Sign in to your account');
    }

    public function test_the_testimonial_settings_keep_the_checkbox_after_an_error(): void
    {
        $this->actingAs($this->user)
            ->from('/settings/testimonials')
            ->followingRedirects()
            ->patch('/settings', ['testimonial_automation' => '1', 'testimonial_email_body' => ''])
            ->assertOk()
            ->assertSee('Write the email body to send testimonial requests.')
            ->assertSeeInOrder(['id="testimonial_automation"', 'checked'], false);
    }

    public function test_the_testimonial_settings_save_with_an_empty_body_when_automation_is_off(): void
    {
        $this->actingAs($this->user)
            ->patch('/settings', ['testimonial_rating' => '1', 'testimonial_email_body' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('1', ClientSetting::query()
            ->where('client_id', $this->client->id)
            ->where('name', ClientSetting::NAME_TESTIMONIAL_RATING)
            ->value('value'));
    }
}
