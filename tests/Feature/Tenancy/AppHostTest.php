<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\ReleaseNote;
use App\Models\User;
use App\Notifications\ReleaseNoteCommented;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * The admin app runs on the APP_URL host (app.feedbackpilgrim.test) and the
 * client boards run on subdomains of APP_DOMAIN (feedbackpilgrim.test). The
 * routes read both values at boot, so this class sets them before the app
 * starts and puts the old values back after each test.
 */
class AppHostTest extends TestCase
{
    use RefreshDatabase;

    private const APP_HOST = 'app.feedbackpilgrim.test';

    private const CLIENT_DOMAIN = 'feedbackpilgrim.test';

    private array $previousEnv = [];

    public function createApplication(): Application
    {
        $this->setEnv('APP_URL', 'http://'.self::APP_HOST);
        $this->setEnv('APP_DOMAIN', self::CLIENT_DOMAIN);

        // The cached env repository remembers what .env wrote at an earlier
        // boot and writes it again. A new repository keeps the values above.
        Env::enablePutenv();

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->previousEnv as $name => $value) {
            if ($value === null) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);

                continue;
            }

            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    private function setEnv(string $name, string $value): void
    {
        if (! array_key_exists($name, $this->previousEnv)) {
            $this->previousEnv[$name] = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    public function test_the_app_host_serves_the_admin_routes(): void
    {
        $this->completeInstanceSetup();

        $this->get('http://'.self::APP_HOST.'/login')->assertOk();
        $this->get('http://'.self::APP_HOST.'/documentation')->assertOk();
    }

    public function test_the_app_host_is_never_a_client_even_when_a_client_has_the_slug_app(): void
    {
        Client::factory()->create(['slug' => 'app']);

        $this->get('http://'.self::APP_HOST.'/iframe/roadmap')->assertNotFound();
        $this->get('http://'.self::APP_HOST.'/iframe/release-notes')->assertNotFound();
    }

    public function test_a_client_subdomain_still_resolves_the_client(): void
    {
        $client = Client::factory()->create(['slug' => 'acme']);

        $this->get('http://acme.'.self::CLIENT_DOMAIN.'/iframe/release-notes')->assertOk();
        // The board uses the scheme of APP_URL, which is http in this test.
        $this->assertSame('http://acme.'.self::CLIENT_DOMAIN, $client->public_site);
    }

    public function test_reserved_subdomains_never_resolve_a_client(): void
    {
        Client::factory()->create(['slug' => 'www']);

        $this->get('http://www.'.self::CLIENT_DOMAIN.'/iframe/release-notes')->assertNotFound();
    }

    public function test_the_root_domain_is_not_a_client(): void
    {
        Client::factory()->create();

        $this->get('http://'.self::CLIENT_DOMAIN.'/iframe/release-notes')->assertNotFound();
    }

    public function test_onboarding_rejects_a_reserved_subdomain(): void
    {
        $user = User::factory()->create(['onboarding' => User::ONBOARDING_CLIENT]);

        foreach (['app', 'www', 'api'] as $slug) {
            $this->actingAs($user)
                ->post('http://'.self::APP_HOST.'/onboarding/project', [
                    'name' => 'Acme Inc',
                    'subdomain' => $slug,
                ])
                ->assertSessionHasErrors('subdomain');
        }

        $this->assertSame(0, Client::query()->whereIn('slug', ['app', 'www', 'api'])->count());
    }

    public function test_the_release_note_comment_email_links_to_the_app_host(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $releaseNote = ReleaseNote::factory()->create(['client_id' => $client->id]);
        $comment = Comment::factory()->create([
            'contact_id' => $contact->id,
            'commentable_id' => $releaseNote->id,
            'commentable_type' => ReleaseNote::class,
        ]);

        $mail = (new ReleaseNoteCommented($releaseNote, $comment))->toMail(User::factory()->create());

        $this->assertSame('http://'.self::APP_HOST."/release-notes/{$releaseNote->id}", $mail->actionUrl);
    }
}
