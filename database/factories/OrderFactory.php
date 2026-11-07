<?php

namespace Database\Factories;

use App\Models\DailyMeal;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $fulfilment = $this->faker->randomElement(['delivery', 'pickup']);

        return [
            'order_number' => 'TB-'.strtoupper(substr(uniqid(), -6)),
            'user_id' => null,
            'fulfilment_type' => $fulfilment,
            'status' => 'received',
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_phone' => $this->faker->phoneNumber(),
            'delivery_address' => $fulfilment === 'delivery'
                ? $this->faker->streetAddress().', Wellington'
                : null,
            'payment_method' => $this->faker->randomElement(['cash', 'card_on_handover']),
            'order_total' => 0,
            'ordered_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
