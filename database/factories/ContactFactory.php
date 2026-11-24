<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => mt_rand(0, 1) ? $this->faker->name : null,
            'email' => $this->faker->safeEmail,
            'ltv' => (mt_rand(10000, 99999) / 100),
            'mrr' => (mt_rand(10000, 99999) / 100),
            'top_customer' => mt_rand(0, 1),
            'notes' => mt_rand(0, 1) ? $this->faker->paragraphs(mt_rand(1, 5), true) : '',
            'avatar' => mt_rand(0, 1) ? $this->faker->imageUrl(240, 240, 'avatar') : null,
            'metadata' => mt_rand(0, 1) ? json_encode([
                'sit' => 'wolrd',
                'amet' => 'hola',
            ]) : null,
        ];
    }
}
