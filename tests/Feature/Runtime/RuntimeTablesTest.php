<?php

namespace Tests\Feature\Runtime;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * With no Redis, the cache, the sessions and the queue use the database.
 * The cache locks (instance setup, sending domains, testimonial import)
 * need the cache_locks table.
 */
class RuntimeTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_drivers_have_their_tables(): void
    {
        foreach (['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "The {$table} table is missing.");
        }
    }

    public function test_the_database_cache_store_gives_locks(): void
    {
        $store = Cache::store('database');

        $store->put('runtime-test', 'value', 60);
        $lock = $store->lock('runtime-test-lock', 10);

        $this->assertSame('value', $store->get('runtime-test'));
        $this->assertTrue($lock->get());
        $this->assertFalse($store->lock('runtime-test-lock', 10)->get());

        $lock->release();
    }
}
