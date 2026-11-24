<?php

namespace Database\Factories;

use App\Models\ClientSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientSetting>
 */
class ClientSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }

    public function domain()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'domain',
                'value' => $this->faker->domainName,
            ];
        });
    }
}
