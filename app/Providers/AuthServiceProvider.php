<?php

namespace App\Providers;

use App\Auth\ActiveUserProvider;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->refuseDeactivatedUsers();
    }

    /**
     * Keep a deactivated user out of every guard.
     *
     * The `web` and `contact` guards find their user through the
     * `active-eloquent` provider. Sanctum reads the user of a personal access
     * token from the token row and skips the provider, so it needs its own
     * check. This check also covers the MCP server. The login form, the
     * contact sign-in link and the public surfaces of a client do their own
     * checks.
     */
    protected function refuseDeactivatedUsers(): void
    {
        Auth::provider('active-eloquent', function ($app, array $config) {
            return new ActiveUserProvider($app['hash'], $config['model']);
        });

        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid) {
            return $isValid
                && ! ($token->tokenable instanceof User && $token->tokenable->isDeactivated());
        });
    }
}
