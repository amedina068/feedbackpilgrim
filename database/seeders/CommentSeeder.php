<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Post::query()->public()->cursor()->each(function ($post) {
            Contact::query()
                ->where('client_id', $post->client_id)
                ->inRandomOrder()
                ->take(mt_rand(5, 10))
                ->cursor()
                ->each(function ($contact) use ($post) {
                    Comment::factory()->times(mt_rand(1, 5))->create([
                        'commentable_id' => $post->id,
                        'commentable_type' => get_class($post),
                        'contact_id' => $contact->id,
                    ]);
                });
        });
    }
}
