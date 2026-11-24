<?php

namespace Database\Factories;

use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => $this->faker->url(),
            'events' => [Webhook::EVENT_POST_CREATED],
            'secret' => Str::random(40),
            'is_active' => true,
        ];
    }

    public function subscribedTo(array $events): static
    {
        return $this->state(fn () => ['events' => $events]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
