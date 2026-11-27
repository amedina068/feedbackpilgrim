<?php

namespace App\Providers;

use App\Contracts\Entitlements;
use App\Support\DashboardBanners;
use App\Support\DashboardNavigation;
use App\Support\UnlimitedEntitlements;
use Aws\SesV2\SesV2Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerExtensionPoints();

        $this->app->bind(SesV2Client::class, function (): SesV2Client {
            $config = config('services.ses');

            return new SesV2Client(array_filter([
                'version' => 'latest',
                'region' => $config['region'],
                'http' => ['connect_timeout' => 5, 'timeout' => 15],
                'credentials' => $config['key'] && $config['secret']
                    ? ['key' => $config['key'], 'secret' => $config['secret']]
                    : null,
            ]));
        });
    }

    /**
     * Bind the extension points that a hosted edition or a plugin can use.
     *
     * Laravel registers the providers of discovered packages before this
     * provider. The "If" methods keep a binding that a package made first.
     * A provider that registers later replaces these bindings.
     */
    private function registerExtensionPoints(): void
    {
        $this->app->singletonIf(Entitlements::class, UnlimitedEntitlements::class);
        $this->app->singletonIf(DashboardNavigation::class);
        $this->app->singletonIf(DashboardBanners::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mail templates print post titles, comments and contact names that
        // contacts of the public site write. Secured encoding escapes the
        // Markdown link and HTML characters in each {{ }} value, so that this
        // text cannot add a link to an email.
        Markdown::withSecuredEncoding();

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('public-content', function (Request $request) {
            return [
                Limit::perMinute(10)->by('contact:'.Auth::guard('contact')->id()),
                Limit::perMinute(30)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('public-votes', function (Request $request) {
            return [
                Limit::perMinute(30)->by('contact:'.Auth::guard('contact')->id()),
                Limit::perMinute(60)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-forms', function (Request $request) {
            return Limit::perMinute(10)->by('auth-forms:'.$request->ip());
        });

        RateLimiter::for('mcp', function (Request $request) {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute(60)->by($token?->id ? 'token:'.$token->id : $request->ip());
        });

        RateLimiter::for('sending-domains', function (Request $request) {
            return Limit::perMinute(5)
                ->by('sending-domains:'.$request->user()->id)
                ->response(fn () => back()->withErrors(['domain' => 'Too many attempts. Wait a minute and try again.']));
        });
    }
}
