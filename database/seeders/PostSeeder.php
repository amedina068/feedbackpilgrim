<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\Stage;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::cursor()->each(function ($client) {
            Stage::query()->inRandomOrder()->where('client_id', $client->id)->get()->each(function ($stage) use ($client) {
                Post::factory()->times(mt_rand(0, 10))->create([
                    'client_id' => $client->id,
                    'stage_position' => $stage->posts()->count(),
                    'stage_id' => $stage->id,
                    'contact_id' => Contact::inRandomOrder()->where('client_id', $client->id)->first()->id,
                ]);
            });
        });
    }
}
