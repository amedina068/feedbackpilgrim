<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->string('votable_type')->after('id');
            $table->unsignedBigInteger('votable_id')->after('votable_type');

            $table->index(['votable_type', 'votable_id']);
        });

        DB::statement(
            "UPDATE votes SET votable_type = 'App\\\\Models\\\\Post', votable_id = post_id WHERE votable_type IS NULL OR votable_type = ''"
        );

        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn('post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->foreignId('post_id')->after('id');
        });

        DB::statement("UPDATE votes SET post_id = votable_id WHERE votable_type = 'App\\\\Models\\\\Post'");

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['votable_type', 'votable_id']);
            $table->dropColumn(['votable_type', 'votable_id']);
        });
    }
};
