<?php

namespace Tests\Feature\Extensions;

use App\Contracts\Entitlements;
use App\Models\User;

/**
 * An Entitlements binding for tests. It sends every user to one URL and
 * refuses or allows new projects, as the test sets it.
 */
class FakeEntitlements implements Entitlements
{
    /** @var list<int> */
    public array $askedFor = [];

    public function __construct(
        private ?string $redirect = null,
        private bool $canCreate = true,
    ) {}

    public function accessRedirect(User $user): ?string
    {
        $this->askedFor[] = $user->id;

        return $this->redirect;
    }

    public function canCreateProject(User $user): bool
    {
        $this->askedFor[] = $user->id;

        return $this->canCreate;
    }

    public function projectLimitMessage(User $user): string
    {
        return 'Your account has 1 project. <b>Upgrade</b> to add more.';
    }
}
