<?php

use App\Models\Client;
use App\Models\Stage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stage becomes the source of truth. Seed the default pipeline per client and
     * point every post at the stage matching its legacy status. posts.status is
     * retained as an auto-synced derived mirror (see Post::booted()).
     */
    public function up(): void
    {
        Client::query()->cursor()->each(function (Client $client) {
            Stage::seedDefaultsFor($client);

            $stagesByType = Stage::query()
                ->where('client_id', $client->id)
                ->orderBy('position')
                ->get()
                ->groupBy('type');

            DB::table('posts')
                ->where('client_id', $client->id)
                ->whereNull('stage_id')
                ->orderBy('id')
                ->each(function ($post) use ($stagesByType) {
                    $type = Stage::typeForLegacyStatus($post->status);
                    $stage = $stagesByType->get($type)?->first()
                        ?? $stagesByType->get(Stage::TYPE_OPEN)?->first();

                    if ($stage) {
                        DB::table('posts')->where('id', $post->id)->update(['stage_id' => $stage->id]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Stage rows and stage_id assignments are left in place; non-destructive.
    }
};
