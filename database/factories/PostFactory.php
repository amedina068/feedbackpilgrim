<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $published = mt_rand(0, 1) ? now() : null;

        return [
            'title' => $this->faker->words(mt_rand(1, 5), true),
            'content' => $this->faker->paragraphs(mt_rand(1, 5), true),
            'notes' => mt_rand(0, 1) ? $this->faker->paragraphs(mt_rand(1, 3), true) : null,
            'published_at' => $published,
            'status' => [
                Post::STATUS_PENDING,
                Post::STATUS_IN_PROGRESS,
                Post::STATUS_COMPLETED,
            ][mt_rand(0, 2)],
            'archived_at' => mt_rand(0, 1) ? now() : null,
        ];
    }
}
