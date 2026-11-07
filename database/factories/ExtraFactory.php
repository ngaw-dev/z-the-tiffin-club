<?php

namespace Database\Factories;

use App\Models\DailyMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExtraFactory extends Factory
{
    public function definition(): array
    {
        return [
            'daily_meal_id' => DailyMeal::factory(),
            'name' => $this->faker->randomElement(['Mango Lassi', 'Extra Naan', 'Gulab Jamun', 'Samosa (2pc)', 'Raita']),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 3, 9),
            'photo_path' => null,
        ];
    }
}
