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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('publishable_key', 64)->nullable()->unique()->after('slug');
            $table->string('legacy_widget_key_hash', 64)->nullable()->unique()->after('publishable_key');
            $table->text('identity_secret')->nullable()->after('legacy_widget_key_hash');
            $table->boolean('require_identity_verification')->default(false)->after('identity_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['publishable_key']);
            $table->dropUnique(['legacy_widget_key_hash']);
            $table->dropColumn([
                'publishable_key',
                'legacy_widget_key_hash',
                'identity_secret',
                'require_identity_verification',
            ]);
        });
    }
};
