<?php

namespace Database\Factories;

use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'color' => $this->faker->hexColor(),
            'type' => Stage::TYPE_OPEN,
            'show_on_roadmap' => false,
            'position' => $this->faker->numberBetween(1, 20),
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function onRoadmap(): static
    {
        return $this->state(fn () => ['show_on_roadmap' => true]);
    }
}
