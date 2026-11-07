<?php

namespace Database\Seeders;

use App\Models\DailyMeal;
use App\Models\Extra;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Tiffin Box Owner',
            'email' => 'owner@thetiffinbox.test',
            'role' => 'owner',
        ]);

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::set($key, $value);
        }

        $meal = DailyMeal::factory()->create();
        $extras = Extra::factory()->count(3)->create(['daily_meal_id' => $meal->id]);

        // A sample guest delivery order with snapshot items.
        $order = Order::factory()->create(['fulfilment_type' => 'delivery', 'order_total' => 0]);
        $items = collect([
            new OrderItem([
                'item_type' => 'meal',
                'daily_meal_id' => $meal->id,
                'name' => $meal->name,
                'unit_price' => $meal->price,
                'quantity' => 2,
            ]),
            new OrderItem([
                'item_type' => 'extra',
                'extra_id' => $extras->first()->id,
                'name' => $extras->first()->name,
                'unit_price' => $extras->first()->price,
                'quantity' => 1,
            ]),
        ]);
        $order->items()->saveMany($items);
        $order->update([
            'order_total' => $items->sum(fn ($i) => $i->unit_price * $i->quantity),
        ]);

        $this->command?->info("Seeded owner: {$owner->email}");
        $this->command?->info("Today's meal: {$meal->name} ({$meal->price}) + {$extras->count()} extras");
    }
}
