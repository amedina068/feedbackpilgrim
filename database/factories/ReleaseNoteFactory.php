<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseNote>
 */
class ReleaseNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'slug' => $this->faker->slug(),
            'published_at' => mt_rand(0, 1) ? now() : null,
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
        ];
    }
}
