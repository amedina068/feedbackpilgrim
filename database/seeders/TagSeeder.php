<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::cursor()->each(function ($client) {
            Tag::factory()->times(mt_rand(5, 10))->create([
                'client_id' => $client->id,
            ]);

            Post::query()
                ->inRandomOrder()
                ->where('client_id', $client->id)
                ->take(mt_rand(1, 10))
                ->get()
                ->each(function ($post) use ($client) {
                    $post->tags()
                        ->sync(
                            Tag::query()
                                ->inRandomOrder()
                                ->where('client_id', $client->id)
                                ->take(mt_rand(1, 10))
                                ->pluck('id')
                        );
                });
        });
    }
}
