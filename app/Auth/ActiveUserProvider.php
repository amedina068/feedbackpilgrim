<?php

namespace App\Auth;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The Eloquent user provider, without deactivated users.
 *
 * The `web` guard (session and remember-me cookie) finds its user through this
 * provider. A deactivated user is not found, so the guard treats the request
 * as a guest. The `contact` guard uses it too: the admin contact of a
 * deactivated user acts as that user on the public site, so it is not found.
 *
 * `retrieveByCredentials()` keeps the deactivated user. The login form needs
 * it to tell the person why the login failed (`LoginRequest`).
 */
class ActiveUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->onlyActive(parent::retrieveById($identifier));
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->onlyActive(parent::retrieveByToken($identifier, $token));
    }

    private function onlyActive(?Authenticatable $user): ?Authenticatable
    {
        if ($user instanceof User && $user->isDeactivated()) {
            return null;
        }

        if ($user instanceof Contact && $user->actsForDeactivatedUser()) {
            return null;
        }

        return $user;
    }
}
