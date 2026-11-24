<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app loads no analytics script. Older installs can still have the
 * Plausible variables in .env, so the test sets them before the app boots.
 */
class NoAnalyticsScriptTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_VARIABLES = [
        'PLAUSIBLE_DOMAIN' => 'feedback.example.com',
        'PLAUSIBLE_SCRIPT_URL' => 'https://plausible.example.com/js/script.js',
    ];

    protected function setUp(): void
    {
        foreach (self::OLD_VARIABLES as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_keys(self::OLD_VARIABLES) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function test_guest_and_signed_in_pages_contain_no_plausible_string(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        $user->adminContactFor($client);

        $dashboardUrl = 'http://'.config('app.domain');

        $pages = [
            'guest login page' => $this->get($dashboardUrl.'/login'),
            'guest public site' => $this->get('http://'.$client->slug.'.'.config('app.domain').'/'),
            'signed-in dashboard' => $this->actingAs($user)->get($dashboardUrl.'/posts'),
        ];

        foreach ($pages as $name => $response) {
            $this->assertSame(200, $response->getStatusCode(), $name);
            $this->assertStringNotContainsStringIgnoringCase('plausible', $response->getContent(), $name);
        }
    }
}
