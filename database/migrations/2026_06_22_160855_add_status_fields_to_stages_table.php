<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('color', 7)->default('#94a3b8')->after('slug');
            $table->string('type')->default('open')->index()->after('color');
            $table->boolean('show_on_roadmap')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn(['slug', 'color', 'type', 'show_on_roadmap']);
        });
    }
};
