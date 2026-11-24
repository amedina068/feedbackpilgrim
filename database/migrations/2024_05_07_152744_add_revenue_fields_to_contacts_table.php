<?php

use App\Models\Contact;
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
        Schema::table('contacts', function (Blueprint $table) {
            $table->float('ltv')->nullable()->after('avatar');
            $table->float('mrr')->nullable()->after('ltv');
        });

        Contact::query()->cursor()->each(function ($contact) {
            $metadataArray = $contact->metadata_array;
            $ltv = $metadataArray['ltv'] ?? 0;
            $mrr = $metadataArray['mrr'] ?? 0;
            unset($metadataArray['ltv']);
            unset($metadataArray['mrr']);

            $contact->update(array_merge(compact('ltv', 'mrr'), [
                'metadata' => json_encode(array_merge($metadataArray, $contact->pilgrimFields), true),
            ]));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            //
        });
    }
};
