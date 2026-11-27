<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The rate limiters and the logs use $request->ip(). The app reads that IP
 * from X-Forwarded-For only when the request comes from a trusted proxy.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/ip', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->secure(),
        ]);
    }

    private function requestFrom(string $remoteAddress)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders([
                'X-Forwarded-For' => '198.51.100.7',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('/_test/ip');
    }

    public function test_an_untrusted_address_keeps_the_real_remote_address(): void
    {
        $this->requestFrom('203.0.113.10')
            ->assertJson(['ip' => '203.0.113.10', 'secure' => false]);
    }

    public function test_a_cloudflare_address_passes_the_forwarded_address(): void
    {
        $this->requestFrom('173.245.48.5')
            ->assertJson(['ip' => '198.51.100.7', 'secure' => true]);

        $this->requestFrom('2606:4700::1')
            ->assertJson(['ip' => '198.51.100.7', 'secure' => true]);
    }

    public function test_the_trusted_proxies_setting_replaces_the_cloudflare_ranges(): void
    {
        config(['trustedproxy.proxies' => $this->loadProxiesWith('10.0.0.0/8, 192.168.1.5')]);

        $this->requestFrom('10.1.2.3')->assertJson(['ip' => '198.51.100.7']);
        $this->requestFrom('192.168.1.5')->assertJson(['ip' => '198.51.100.7']);
        $this->requestFrom('173.245.48.5')->assertJson(['ip' => '173.245.48.5']);
    }

    public function test_the_loopback_addresses_are_always_trusted(): void
    {
        $this->assertContains('127.0.0.1', $this->loadProxiesWith(''));
        $this->assertContains('::1', $this->loadProxiesWith(''));
        $this->assertContains('127.0.0.1', $this->loadProxiesWith('10.0.0.0/8'));

        $this->requestFrom('127.0.0.1')->assertJson(['ip' => '198.51.100.7', 'secure' => true]);
    }

    public function test_an_empty_trusted_proxies_setting_uses_the_cloudflare_ranges(): void
    {
        $proxies = $this->loadProxiesWith('');

        $this->assertContains('173.245.48.0/20', $proxies);
        $this->assertContains('2606:4700::/32', $proxies);
        $this->assertNotContains('*', $proxies);
    }

    /**
     * @return list<string>
     */
    private function loadProxiesWith(string $value): array
    {
        $_SERVER['TRUSTED_PROXIES'] = $_ENV['TRUSTED_PROXIES'] = $value;

        try {
            return (require config_path('trustedproxy.php'))['proxies'];
        } finally {
            unset($_SERVER['TRUSTED_PROXIES'], $_ENV['TRUSTED_PROXIES']);
        }
    }
}
