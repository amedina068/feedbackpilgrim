<?php

namespace App\Support;

use App\Contracts\Entitlements;
use App\Models\User;

/**
 * The default Entitlements of the core. Each user can use the dashboard and
 * can create any number of projects.
 */
class UnlimitedEntitlements implements Entitlements
{
    public function accessRedirect(User $user): ?string
    {
        return null;
    }

    public function canCreateProject(User $user): bool
    {
        return true;
    }

    public function projectLimitMessage(User $user): string
    {
        return 'You cannot create more projects on this account.';
    }
}
