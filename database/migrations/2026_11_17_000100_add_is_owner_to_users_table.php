<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The owner runs the instance and is the only user that can open the admin
 * area. Feedback Pilgrim had no instance admin before this flag. The first
 * user of an existing instance is its operator, so that user becomes the
 * owner here. To make a different user the owner, run
 * `php artisan fp:install --promote=<email>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false);
        });

        $firstUserId = DB::table('users')->min('id');

        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update(['is_owner' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
