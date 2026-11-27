<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Docker image starts with no environment variables. The config files
 * derive the URL, the cookie and the Sanctum values from APP_URL or
 * APP_DOMAIN. A value that the operator sets always wins.
 */
class ZeroConfigTest extends TestCase
{
    /**
     * The variables that the derived values read. The tests unset all of
     * them first, so that phpunit.xml and .env do not change the result.
     */
    private const VARIABLES = [
        'APP_NAME', 'APP_URL', 'APP_DOMAIN', 'APP_KEY', 'APP_KEY_FILE',
        'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE', 'SESSION_COOKIE',
        'SANCTUM_STATEFUL_DOMAINS',
        'DB_BUSY_TIMEOUT', 'DB_JOURNAL_MODE', 'DB_SYNCHRONOUS', 'DB_TRANSACTION_MODE',
    ];

    public function test_no_variables_give_a_working_localhost_instance(): void
    {
        $config = $this->loadConfig([]);

        $this->assertSame('Feedback Pilgrim', $config['app']['name']);
        $this->assertSame('http://localhost:8080', $config['app']['url']);
        $this->assertSame('localhost', $config['app']['host']);
        $this->assertSame('localhost', $config['app']['domain']);
        $this->assertNull($config['session']['domain']);
        $this->assertFalse($config['session']['secure']);
        $this->assertSame('feedback_pilgrim_session', $config['session']['cookie']);
        $this->assertContains('localhost:8080', $config['sanctum']['stateful']);
        $this->assertContains('*.localhost:8080', $config['sanctum']['stateful']);
    }

    public function test_an_empty_app_url_counts_as_not_set(): void
    {
        // A compose env_file with "APP_URL=" defines the variable as "".
        $config = $this->loadConfig(['APP_URL' => '', 'APP_DOMAIN' => '']);

        $this->assertSame('http://localhost:8080', $config['app']['url']);
        $this->assertSame('localhost', $config['app']['domain']);
    }

    public function test_app_domain_alone_gives_an_https_instance(): void
    {
        $config = $this->loadConfig(['APP_DOMAIN' => 'feedback.example.com']);

        $this->assertSame('https://feedback.example.com', $config['app']['url']);
        $this->assertSame('feedback.example.com', $config['app']['host']);
        $this->assertSame('feedback.example.com', $config['app']['domain']);
        $this->assertSame('.feedback.example.com', $config['session']['domain']);
        $this->assertTrue($config['session']['secure']);
        $this->assertContains('feedback.example.com', $config['sanctum']['stateful']);
        $this->assertContains('*.feedback.example.com', $config['sanctum']['stateful']);
    }

    public function test_app_url_alone_gives_the_domain_and_the_port(): void
    {
        $config = $this->loadConfig(['APP_URL' => 'http://feedback.test:8000']);

        $this->assertSame('feedback.test', $config['app']['domain']);
        $this->assertSame('.feedback.test', $config['session']['domain']);
        $this->assertFalse($config['session']['secure']);
        $this->assertContains('feedback.test:8000', $config['sanctum']['stateful']);
        $this->assertContains('*.feedback.test:8000', $config['sanctum']['stateful']);
    }

    public function test_an_ip_address_gets_no_cookie_domain(): void
    {
        $config = $this->loadConfig(['APP_URL' => 'http://192.168.1.10:8080']);

        $this->assertSame('192.168.1.10', $config['app']['domain']);
        $this->assertNull($config['session']['domain']);
    }

    public function test_the_values_that_the_operator_sets_win(): void
    {
        $config = $this->loadConfig([
            'APP_NAME' => 'Acme Feedback',
            'APP_URL' => 'https://app.feedbackpilgrim.com',
            'APP_DOMAIN' => 'feedbackpilgrim.com',
            'SESSION_DOMAIN' => '.feedbackpilgrim.com',
            'SESSION_SECURE_COOKIE' => 'false',
            'SANCTUM_STATEFUL_DOMAINS' => 'feedbackpilgrim.com,*.feedbackpilgrim.com',
        ]);

        $this->assertSame('Acme Feedback', $config['app']['name']);
        $this->assertSame('https://app.feedbackpilgrim.com', $config['app']['url']);
        $this->assertSame('app.feedbackpilgrim.com', $config['app']['host']);
        $this->assertSame('feedbackpilgrim.com', $config['app']['domain']);
        $this->assertSame('.feedbackpilgrim.com', $config['session']['domain']);
        $this->assertFalse($config['session']['secure']);
        $this->assertSame('acme_feedback_session', $config['session']['cookie']);
        $this->assertSame(['feedbackpilgrim.com', '*.feedbackpilgrim.com'], $config['sanctum']['stateful']);
    }

    public function test_a_null_session_domain_stays_null(): void
    {
        $config = $this->loadConfig(['APP_DOMAIN' => 'feedback.example.com', 'SESSION_DOMAIN' => 'null']);

        $this->assertNull($config['session']['domain']);
    }

    public function test_the_app_key_comes_from_the_secrets_file_when_the_variable_is_empty(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fp-key');
        file_put_contents($file, "base64:ZmlsZWtleWZpbGVrZXlmaWxla2V5ZmlsZWtleTA=\n");

        try {
            $fromFile = $this->loadConfig(['APP_KEY' => '', 'APP_KEY_FILE' => $file]);
            $fromVariable = $this->loadConfig(['APP_KEY' => 'base64:dmFyaWFibGU=', 'APP_KEY_FILE' => $file]);
            $noFile = $this->loadConfig(['APP_KEY_FILE' => $file.'.missing']);
        } finally {
            unlink($file);
        }

        $this->assertSame('base64:ZmlsZWtleWZpbGVrZXlmaWxla2V5ZmlsZWtleTA=', $fromFile['app']['key']);
        $this->assertSame('base64:dmFyaWFibGU=', $fromVariable['app']['key']);
        $this->assertNull($noFile['app']['key']);
    }

    public function test_sqlite_uses_wal_and_waits_for_locks(): void
    {
        $sqlite = $this->loadConfig([])['database']['connections']['sqlite'];

        $this->assertSame(5000, $sqlite['busy_timeout']);
        $this->assertSame('wal', $sqlite['journal_mode']);
        $this->assertSame('normal', $sqlite['synchronous']);
        $this->assertSame('IMMEDIATE', $sqlite['transaction_mode']);
    }

    public function test_the_sqlite_settings_come_from_the_environment(): void
    {
        $sqlite = $this->loadConfig([
            'DB_BUSY_TIMEOUT' => '1000',
            'DB_JOURNAL_MODE' => 'delete',
            'DB_SYNCHRONOUS' => 'full',
            'DB_TRANSACTION_MODE' => 'DEFERRED',
        ])['database']['connections']['sqlite'];

        $this->assertSame(1000, $sqlite['busy_timeout']);
        $this->assertSame('delete', $sqlite['journal_mode']);
        $this->assertSame('full', $sqlite['synchronous']);
        $this->assertSame('DEFERRED', $sqlite['transaction_mode']);
    }

    /**
     * Load the config files with only the given variables set.
     *
     * @param  array<string, string>  $variables
     * @return array<string, array<string, mixed>>
     */
    private function loadConfig(array $variables): array
    {
        $saved = [];

        foreach (self::VARIABLES as $name) {
            $saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }

        foreach ($variables as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }

        try {
            $config = [];

            foreach (['app', 'session', 'sanctum', 'database'] as $file) {
                $config[$file] = require config_path($file.'.php');
            }

            return $config;
        } finally {
            foreach ($saved as $name => [$server, $env, $putenv]) {
                unset($_SERVER[$name], $_ENV[$name]);

                if ($server !== null) {
                    $_SERVER[$name] = $server;
                }

                if ($env !== null) {
                    $_ENV[$name] = $env;
                }

                putenv($putenv === false ? $name : "{$name}={$putenv}");
            }
        }
    }
}
