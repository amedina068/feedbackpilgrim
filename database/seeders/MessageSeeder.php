<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::query()->cursor()->each(function ($client) {
            for ($x = 0; $x < 20; $x++) {
                Message::factory()->times(mt_rand(1, 5))->create([
                    'client_id' => $client->id,
                    'contact_id' => Contact::factory()->create([
                        'client_id' => $client->id,
                    ])->id,
                ]);
            }
        });
    }
}
