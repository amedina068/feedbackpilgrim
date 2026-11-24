<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id');
            $table->foreignId('stage_id')->nullable();
            $table->foreignId('contact_id')->nullable();
            $table->string('title');
            $table->text('content')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable()->index();
            $table->unsignedInteger('stage_position')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
