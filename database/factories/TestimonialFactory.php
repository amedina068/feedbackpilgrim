<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => $this->faker->paragraph(),
            'rating' => $this->faker->numberBetween(1, 5),
            'username' => $this->faker->userName(),
            'client_id' => Client::factory(),
            'contact_id' => null,
            'approved_at' => null,
        ];
    }
}
