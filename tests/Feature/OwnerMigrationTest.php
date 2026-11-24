<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feedback Pilgrim had no instance admin before the owner flag. The migration
 * makes the first user the owner, so the operator of an existing instance
 * keeps access to the admin area.
 */
class OwnerMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_17_000100_add_is_owner_to_users_table.php';

    private function runMigration(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->down();
        $migration->up();
    }

    public function test_the_first_user_becomes_the_owner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->runMigration();

        $this->assertTrue($first->fresh()->isOwner());
        $this->assertFalse($second->fresh()->isOwner());
    }

    public function test_an_empty_instance_gets_no_owner(): void
    {
        $this->runMigration();

        $this->assertDatabaseCount('users', 0);
    }
}
