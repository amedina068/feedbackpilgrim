<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Publish button set only `status`, but the public site, the widget and the API read
 * `published_at`. Copy each publication into `published_at`, then remove `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('release_notes')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);

        Schema::table('release_notes', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('release_notes', function (Blueprint $table) {
            $table->string('status')->default('draft')->index()->after('published_at');
        });

        DB::table('release_notes')->whereNotNull('published_at')->update(['status' => 'published']);
    }
};
