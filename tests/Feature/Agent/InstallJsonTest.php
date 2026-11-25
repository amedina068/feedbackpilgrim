<?php

namespace Tests\Feature\Agent;

use App\Models\Client;
use App\Models\User;
use App\Support\ApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * `fp:install --json` is the one command that an agent runs to set up a new
 * instance. It is idempotent and needs no password.
 */
class InstallJsonTest extends TestCase
{
    use RefreshDatabase;
    use RunsJsonCommands;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{exit: int, ok: bool, data: array<string, mixed>, errors: list<array{code: string, message: string, fix: string|null}>}
     */
    private function install(array $overrides = []): array
    {
        return $this->runJson('fp:install', array_merge([
            '--name' => 'Jane Owner',
            '--email' => 'jane@example.com',
            '--project' => 'Acme',
            '--subdomain' => 'acme',
        ], $overrides));
    }

    public function test_a_fresh_install_with_a_token_returns_the_login_url_the_token_and_the_connect_lines(): void
    {
        $result = $this->install(['--with-token' => 'agent']);

        $this->assertSame(0, $result['exit']);
        $this->assertSame([], $result['errors']);

        $data = $result['data'];
        $this->assertSame('created', $data['status']);
        $this->assertSame('jane@example.com', $data['owner']['email']);
        $this->assertSame('acme', $data['project']['subdomain']);
        $this->assertSame('Acme', $data['project']['name']);
        $this->assertSame('http://localhost', $data['app_url']);
        $this->assertStringContainsString('/login/link/', $data['login_url']);
        $this->assertNotNull($data['login_url_expires_at']);
        $this->assertFalse($data['password_set']);

        $this->assertSame('agent', $data['token']['name']);
        $this->assertMatchesRegularExpression('/^\d+\|\S+$/', $data['token']['plain_text']);
        $this->assertSame(ApiTokens::PRODUCT_ABILITIES, $data['token']['abilities']);
        $this->assertNull($data['token']['expires_at']);

        $this->assertSame('http://localhost/mcp', $data['connect']['mcp_url']);
        $this->assertSame("export FP_TOKEN='{$data['token']['plain_text']}'", $data['connect']['env']);
        $this->assertSame(
            'claude mcp add --transport http feedback-pilgrim http://localhost/mcp --header "Authorization: Bearer ${FP_TOKEN}"',
            $data['connect']['claude'],
        );
        $this->assertSame(
            'codex mcp add feedback-pilgrim --url http://localhost/mcp --bearer-token-env-var FP_TOKEN',
            $data['connect']['codex'],
        );
    }

    public function test_the_install_token_works_on_the_mcp_server(): void
    {
        $token = $this->install(['--with-token' => 'agent'])['data']['token']['plain_text'];

        $response = $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertContains('create_post', $response->json('result.tools.*.name'));
    }

    public function test_the_install_needs_no_password(): void
    {
        $this->install();

        $owner = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($owner->isOwner());
        $this->assertNotEmpty($owner->password);
    }

    public function test_the_deprecated_password_option_still_sets_the_password(): void
    {
        $result = $this->install(['--password' => 'correct-horse-battery']);

        $this->assertTrue($result['data']['password_set']);
        $this->assertTrue(Hash::check('correct-horse-battery', User::query()->firstOrFail()->password));
    }

    public function test_without_a_token_the_token_and_the_connect_lines_are_null(): void
    {
        $data = $this->install()['data'];

        $this->assertNull($data['token']);
        $this->assertNull($data['connect']);
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_second_run_returns_exists_and_changes_nothing(): void
    {
        $this->install(['--with-token' => 'agent']);

        $result = $this->install(['--with-token' => 'agent']);

        $this->assertSame(0, $result['exit']);
        $this->assertSame('exists', $result['data']['status']);
        $this->assertSame('jane@example.com', $result['data']['owner']['email']);
        $this->assertNull($result['data']['token']);
        $this->assertStringContainsString('fp:token:create', implode(' ', $result['data']['next']));
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Client::query()->count());
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_a_run_with_another_email_on_a_used_instance_is_a_conflict(): void
    {
        $this->install();

        $result = $this->install(['--email' => 'other@example.com']);

        $this->assertSame(3, $result['exit']);
        $this->assertSame('instance_has_owner', $result['errors'][0]['code']);
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_missing_email_is_bad_input(): void
    {
        $result = $this->runJson('fp:install', ['--name' => 'Jane Owner']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('missing_option', $result['errors'][0]['code']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_an_invalid_email_is_bad_input(): void
    {
        $result = $this->install(['--email' => 'not-an-email']);

        $this->assertSame(2, $result['exit']);
        $this->assertSame('invalid_email', $result['errors'][0]['code']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_the_project_and_the_subdomain_have_defaults(): void
    {
        $result = $this->runJson('fp:install', ['--name' => 'Jane Owner', '--email' => 'jane@example.com']);

        $this->assertSame(0, $result['exit']);
        $this->assertSame('My product', $result['data']['project']['name']);
        $this->assertSame('my-product', $result['data']['project']['subdomain']);
    }

    public function test_prepare_with_json_reports_prepared(): void
    {
        $result = $this->runJson('fp:install', ['--prepare' => true]);

        $this->assertSame(0, $result['exit']);
        $this->assertSame('prepared', $result['data']['status']);
    }
}
