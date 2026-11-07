<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DailyMealFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_date' => today(),
            'name' => 'Butter Chicken Tiffin',
            'description' => 'Slow-cooked butter chicken with jeera rice, naan and salad.',
            'price' => $this->faker->randomFloat(2, 15, 25),
            'photo_path' => null,
            'max_per_order' => 5,
        ];
    }

    public function forDate(\DateTimeInterface $date): static
    {
        return $this->state(fn () => ['menu_date' => $date]);
    }
}
