<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['client_id', 'created_at'], 'activity_client_created');
            $table->index(['client_id', 'causer_id', 'created_at'], 'activity_client_causer_created');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['client_id', 'created_at'], 'posts_client_created');
            $table->index(['client_id', 'published_at'], 'posts_client_published');
            $table->index(['contact_id', 'created_at'], 'posts_contact_created');
            $table->index(['contact_id', 'published_at'], 'posts_contact_published');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['contact_id', 'created_at'], 'comments_contact_created');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->index(['contact_id', 'created_at'], 'votes_contact_created');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['client_id', 'created_at'], 'contacts_client_created');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_client_created');
            $table->dropIndex('activity_client_causer_created');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_client_created');
            $table->dropIndex('posts_client_published');
            $table->dropIndex('posts_contact_created');
            $table->dropIndex('posts_contact_published');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_contact_created');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('votes_contact_created');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_client_created');
        });
    }
};
