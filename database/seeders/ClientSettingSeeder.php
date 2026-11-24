<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientSetting;
use Illuminate\Database\Seeder;

class ClientSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::query()->cursor()->each(function ($client) {
            ClientSetting::factory()->domain()->create([
                'client_id' => $client->id,
            ]);
        });
    }
}
