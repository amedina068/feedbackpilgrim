<?php

namespace Tests\Feature\Runtime;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The NOCASE migration rebuilds the users and contacts tables on SQLite.
 * An existing SQLite instance must keep its rows, and the rows that point
 * at a contact must keep the link.
 *
 * The test uses its own SQLite file and no test transaction, because the
 * migration runs outside a transaction in production.
 */
class EmailCaseMigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_25_000200_make_emails_case_insensitive_on_sqlite.php';

    private string $file;

    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'fp-nocase').'.sqlite';
        touch($this->file);

        config(['database.connections.nocase' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->file],
        )]);

        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('nocase');

        Artisan::call('migrate', ['--database' => 'nocase', '--force' => true, '--schema-path' => $this->file.'.none']);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('nocase');
        @unlink($this->file);

        parent::tearDown();
    }

    public function test_the_rebuild_keeps_the_rows_and_the_links(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->down();

        $now = now()->toDateTimeString();
        $userId = User::factory()->create(['email' => 'Ada@Example.com'])->id;
        $clientId = Client::factory()->create()->id;
        $contactId = Contact::factory()->create(['client_id' => $clientId, 'email' => 'Grace@Example.com'])->id;
        $testimonialId = DB::table('testimonials')->insertGetId([
            'client_id' => $clientId, 'contact_id' => $contactId, 'description' => 'Great', 'username' => 'grace',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $migration->up();

        $this->assertSame($userId, DB::table('users')->where('email', 'ada@example.com')->value('id'));
        $this->assertSame($contactId, DB::table('contacts')->where('email', 'grace@example.com')->value('id'));
        $this->assertSame($contactId, DB::table('testimonials')->where('id', $testimonialId)->value('contact_id'));
        $this->assertSame(1, DB::scalar('pragma foreign_keys'));
    }

    public function test_emails_that_differ_only_in_case_keep_the_users_table(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->down();

        $now = now()->toDateTimeString();
        foreach (['Ada@Example.com', 'ada@example.com'] as $email) {
            DB::table('users')->insert([
                'name' => 'Ada', 'email' => $email, 'password' => 'x', 'onboarding' => 'done',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $migration->up();

        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame(1, DB::table('users')->where('email', 'ada@example.com')->count());
    }
}
