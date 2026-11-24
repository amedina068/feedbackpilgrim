<?php

use App\Models\Board;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable()->after('client_id')->index();
        });

        Client::query()->cursor()->each(function (Client $client) {
            $board = Board::seedDefaultFor($client);

            DB::table('posts')
                ->where('client_id', $client->id)
                ->whereNull('board_id')
                ->update(['board_id' => $board->id]);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('board_id');
        });
    }
};
