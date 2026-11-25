<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A signed URL that logs a user in one time.
 *
 * The URL holds a hash of the remember token of the user. The first login
 * rotates that token, so a used link no longer matches. Each logout and each
 * `fp:user:password` also rotates it, so those also end all open links.
 *
 * A user who logs in with a link can set a password without the current
 * password, one time. The session key below carries that permission.
 */
class LoginLink
{
    public const ROUTE = 'login.link';

    /** The session key that lets the user set a password without the current one. */
    public const SESSION_KEY = 'login_link.can_set_password';

    public const INSTALL_MINUTES = 60;

    public const INVITE_DAYS = 7;

    public static function make(User $user, CarbonInterface $expiresAt): string
    {
        if (! $user->getRememberToken()) {
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        }

        return URL::temporarySignedRoute(self::ROUTE, $expiresAt, [
            'user' => $user->getKey(),
            'version' => self::version($user),
        ]);
    }

    /**
     * True while the link matches the current remember token of the user.
     */
    public static function isCurrent(User $user, ?string $version): bool
    {
        return is_string($version)
            && $user->getRememberToken()
            && hash_equals(self::version($user), $version);
    }

    /**
     * Make each link of the user invalid.
     */
    public static function consume(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    private static function version(User $user): string
    {
        return substr(hash_hmac('sha256', 'login-link|'.$user->getKey().'|'.$user->getRememberToken(), (string) config('app.key')), 0, 32);
    }
}
