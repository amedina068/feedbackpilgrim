<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL compares emails without case (utf8mb4_unicode_ci). SQLite compares
 * text with BINARY by default, so "Ada@Example.com" did not match
 * "ada@example.com". This migration gives the email columns the NOCASE
 * collation on SQLite. It does nothing on MySQL.
 *
 * SQLite changes a column with a table rebuild. The rebuild turns off the
 * foreign keys first, and that pragma has no effect inside a transaction.
 * So this migration runs outside a transaction. Without that, the drop of
 * the old contacts table would clear testimonials.contact_id.
 */
return new class extends Migration
{
    private const PATH = 'database/migrations/2026_11_25_000200_make_emails_case_insensitive_on_sqlite.php';

    public $withinTransaction = false;

    public function up(): void
    {
        $this->setCollation('NOCASE');
    }

    public function down(): void
    {
        $this->setCollation('BINARY');
    }

    private function setCollation(string $collation): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        if ($collation === 'NOCASE' && $this->usersHaveEmailsThatDifferOnlyInCase()) {
            Log::warning('Two or more users have the same email in a different case. The users.email column keeps the BINARY collation. Give each user a unique email. Then roll back and run this migration again with --path='.self::PATH);
        } else {
            Schema::table('users', function (Blueprint $table) use ($collation) {
                $table->string('email')->collation($collation)->change();
            });
        }

        Schema::table('contacts', function (Blueprint $table) use ($collation) {
            $table->string('email')->nullable()->collation($collation)->change();
        });
    }

    private function usersHaveEmailsThatDifferOnlyInCase(): bool
    {
        return DB::table('users')
            ->selectRaw('lower(email) as normalized_email')
            ->groupByRaw('lower(email)')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
