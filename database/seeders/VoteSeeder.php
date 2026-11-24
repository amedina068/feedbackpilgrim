<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Database\Seeder;

class VoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Post::query()->cursor()->each(function ($post) {
            Contact::query()
                ->take(mt_rand(10, 20))
                ->where('client_id', $post->client_id)
                ->inRandomOrder()
                ->get()
                ->each(function ($contact) use ($post) {
                    Vote::factory()->create([
                        'votable_id' => $post->id,
                        'votable_type' => get_class($post),
                        'contact_id' => $contact->id,
                    ]);
                });
        });
    }
}
