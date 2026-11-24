<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => $this->faker->sentences(mt_rand(1, 5), true),
            'status_id' => mt_rand(0, 1),
            'notes' => mt_rand(0, 1) ? $this->faker->paragraphs(mt_rand(1, 3), true) : null,
        ];
    }
}
