<?php

namespace Tests\Unit\Models;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WeeklyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Characterization tests for model casts, booted hooks, scopes, and
 * relationships. These pin current behavior so a framework upgrade that
 * silently changes Eloquent semantics is caught immediately.
 */
class ModelBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_casts_and_hashing(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNotSame('password', $user->password);
    }

    public function test_user_access_gate_and_onboarding_step(): void
    {
        $completed = User::factory()->create([
            'onboarding' => User::ONBOARDING_COMPLETED,
        ]);
        $this->assertTrue($completed->hasAccess());
        $this->assertSame('/dashboard', $completed->onboardingStep());

        $client = User::factory()->create([
            'onboarding' => User::ONBOARDING_CLIENT,
        ]);
        $this->assertFalse($client->hasAccess());
        $this->assertSame('/onboarding/project', $client->onboardingStep());
    }

    public function test_user_relationships(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        Contact::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);

        $this->assertTrue($user->clients->contains($client));
        $this->assertCount(1, $user->contacts);
    }

    public function test_client_seeds_default_settings_on_creation(): void
    {
        $client = Client::factory()->create();

        $names = $client->settings()->pluck('name')->all();

        $this->assertCount(3, $names);
        $this->assertContains(ClientSetting::NAME_TESTIMONIAL_RATING, $names);
        $this->assertContains(ClientSetting::NAME_TESTIMONIAL_AUTOMATION, $names);
        $this->assertContains(ClientSetting::NAME_TESTIMONIAL_EMAIL_BODY, $names);
    }

    public function test_client_public_site_uses_the_scheme_of_the_app_url(): void
    {
        $client = Client::factory()->create(['slug' => 'acme']);
        $domain = config('app.domain');

        config(['app.url' => 'http://'.$domain]);
        $this->assertSame('http://acme.'.$domain, $client->public_site);
        $this->assertSame('http://acme.'.$domain.'/iframe/release-notes', $client->releaseNotesIframeUrl);

        config(['app.url' => 'https://'.$domain]);
        $this->assertSame('https://acme.'.$domain, $client->public_site);
    }

    public function test_client_public_site_keeps_the_port_of_the_app_url(): void
    {
        $client = Client::factory()->create(['slug' => 'acme']);
        $domain = config('app.domain');

        config(['app.url' => 'http://'.$domain.':8080']);

        $this->assertSame('http://acme.'.$domain.':8080', $client->public_site);
    }

    public function test_contact_generates_uuid_on_creation(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $this->assertNotEmpty($contact->uuid);
        $this->assertSame($client->id, $contact->client->id);
    }

    public function test_release_note_slugifies_title_on_creation(): void
    {
        $note = ReleaseNote::factory()->create(['title' => 'Big New Feature']);

        $this->assertStringStartsWith('big-new-feature', $note->slug);
        $this->assertInstanceOf(Carbon::class, $note->published_at ?? now());
    }

    public function test_release_note_published_scope(): void
    {
        $client = Client::factory()->create();
        ReleaseNote::factory()->create(['client_id' => $client->id, 'published_at' => now()]);
        ReleaseNote::factory()->create(['client_id' => $client->id, 'published_at' => null]);

        $this->assertCount(1, ReleaseNote::query()->published()->get());
    }

    public function test_post_public_scope_and_status_helpers(): void
    {
        $client = Client::factory()->create();
        Post::factory()->create(['client_id' => $client->id, 'published_at' => now()]);
        Post::factory()->create(['client_id' => $client->id, 'published_at' => null]);

        $this->assertCount(1, Post::query()->public()->get());

        $pending = Post::factory()->create(['client_id' => $client->id, 'status' => Post::STATUS_PENDING]);
        $done = Post::factory()->create(['client_id' => $client->id, 'status' => Post::STATUS_COMPLETED]);

        $this->assertTrue($pending->pending());
        $this->assertFalse($pending->completed());
        $this->assertTrue($done->completed());
    }

    public function test_post_morph_relationships(): void
    {
        $client = Client::factory()->create();
        $post = Post::factory()->create(['client_id' => $client->id]);
        $tag = Tag::factory()->create(['client_id' => $client->id]);

        $post->tags()->attach($tag);

        $this->assertTrue($post->tags->contains($tag));
        $this->assertTrue($tag->posts->contains($post));
    }

    public function test_testimonial_approval_and_scopes(): void
    {
        $client = Client::factory()->create();
        $testimonial = Testimonial::factory()->create(['client_id' => $client->id, 'approved_at' => null]);

        $this->assertFalse($testimonial->is_approved);
        $this->assertCount(1, Testimonial::query()->pending()->get());

        $testimonial->approve();
        $this->assertInstanceOf(Carbon::class, $testimonial->fresh()->approved_at);
        $this->assertTrue($testimonial->fresh()->is_approved);
        $this->assertCount(1, Testimonial::query()->approved()->get());

        $testimonial->decline();
        $this->assertNull($testimonial->fresh()->approved_at);
    }

    public function test_message_status_helpers(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);

        $pending = Message::factory()->create(['contact_id' => $contact->id, 'client_id' => $client->id, 'status_id' => Message::STATUS_PENDING]);
        $resolved = Message::factory()->create(['contact_id' => $contact->id, 'client_id' => $client->id, 'status_id' => Message::STATUS_RESOLVED]);

        $this->assertTrue($pending->pending());
        $this->assertFalse($pending->resolved());
        $this->assertTrue($resolved->resolved());
    }

    public function test_weekly_report_casts(): void
    {
        $report = new WeeklyReport([
            'metrics' => ['posts' => 5, 'votes' => 12],
            'week_start' => '2026-01-05',
            'week_end' => '2026-01-11',
        ]);

        $this->assertIsArray($report->metrics);
        $this->assertSame(5, $report->metrics['posts']);
        $this->assertInstanceOf(Carbon::class, $report->week_start);
    }
}
