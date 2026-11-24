<?php

namespace Database\Factories;

use App\Models\FormSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSetting>
 */
class FormSettingFactory extends Factory
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

    public function buttonBackgroundColor(): Factory
    {
        return $this->state(function () {
            return [
                'name' => FormSetting::NAME_BUTTON_BACKGROUND_COLOR,
                'value' => 'rgb(220 38 38)',
            ];
        });
    }

    public function buttonTextColor(): Factory
    {
        return $this->state(function () {
            return [
                'name' => FormSetting::NAME_BUTTON_TEXT_COLOR,
                'value' => '#FFFFFF',
            ];
        });
    }

    public function avatarPhoto(): Factory
    {
        return $this->state(function () {
            return [
                'name' => FormSetting::NAME_AVATAR_PHOTO,
                'value' => $this->faker->imageUrl(360, 360, 'animals', true, 'cats'),
            ];
        });
    }

    public function welcomeMessage(): Factory
    {
        return $this->state(function () {
            return [
                'name' => FormSetting::NAME_WELCOME_MESSAGE,
                'value' => $this->faker->sentence,
            ];
        });
    }
}
