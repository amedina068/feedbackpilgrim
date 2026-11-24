<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::query()->cursor()->each(fn ($client) => Contact::factory()->times(25)->create([
            'client_id' => $client->id,
        ]));
    }
}
