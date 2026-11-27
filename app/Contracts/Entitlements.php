<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Decides what a dashboard user can do on this instance.
 *
 * The core binds UnlimitedEntitlements, so a self-hosted instance has no
 * limits. A hosted edition or a plugin can bind its own implementation in a
 * service provider. The core never asks this binding about the owner of the
 * instance.
 */
interface Entitlements
{
    /** Null when the user can use the dashboard. Else the URL to send the user to, for example a billing page. */
    public function accessRedirect(User $user): ?string;

    /** True when the user can create one more project (a Client). */
    public function canCreateProject(User $user): bool;

    /** The message the dashboard shows when canCreateProject() is false. */
    public function projectLimitMessage(User $user): string;
}
