<?php

namespace Database\Factories;

use App\Models\Board;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => $this->faker->numberBetween(1, 20),
            'is_public' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
