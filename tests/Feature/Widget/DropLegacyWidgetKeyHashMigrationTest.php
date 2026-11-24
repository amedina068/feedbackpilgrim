<?php

namespace Tests\Feature\Widget;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The widget accepts only the publishable key. The sha256 of the revoked
 * public tokens has no use, so the migration drops its column.
 */
class DropLegacyWidgetKeyHashMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_18_000100_drop_legacy_widget_key_hash_from_clients_table.php';

    public function test_the_clients_table_has_no_legacy_widget_key_hash_column(): void
    {
        $this->assertFalse(Schema::hasColumn('clients', 'legacy_widget_key_hash'));
    }

    public function test_down_adds_the_column_back_empty(): void
    {
        $client = Client::factory()->create();
        $migration = require database_path('migrations/'.self::MIGRATION);

        $migration->down();

        $this->assertTrue(Schema::hasColumn('clients', 'legacy_widget_key_hash'));
        $this->assertNull(DB::table('clients')->where('id', $client->id)->value('legacy_widget_key_hash'));

        $migration->up();

        $this->assertFalse(Schema::hasColumn('clients', 'legacy_widget_key_hash'));
    }
}
