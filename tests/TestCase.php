<?php

namespace Tests;

use App\Models\User;
use App\Support\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->fakeDns([]);
    }

    /**
     * Replace DNS lookups with fixed records, so that no test sends a DNS
     * query. A host that is not in the list does not resolve.
     *
     * @param  array<string, list<string>>  $records
     */
    protected function fakeDns(array $records): void
    {
        $this->app->instance(HostResolver::class, new class($records) extends HostResolver
        {
            /**
             * @param  array<string, list<string>>  $records
             */
            public function __construct(private array $records) {}

            public function resolve(string $host): array
            {
                return $this->records[$host] ?? [];
            }
        });
    }

    /**
     * Create one account, so that the instance is set up. While the users
     * table is empty, /, /login and /register go to the /setup page.
     */
    protected function completeInstanceSetup(): User
    {
        return User::factory()->create();
    }
}
