<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column kept the sha256 of each revoked public widget token. The widget
 * accepts only the publishable key now, so the hashes have no use.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['legacy_widget_key_hash']);
            $table->dropColumn('legacy_widget_key_hash');
        });
    }

    /**
     * Reverse the migrations. The hashes do not come back.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('legacy_widget_key_hash', 64)->nullable()->unique()->after('publishable_key');
        });
    }
};
