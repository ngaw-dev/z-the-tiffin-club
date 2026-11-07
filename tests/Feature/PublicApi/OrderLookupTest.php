<?php

namespace Tests\Feature\PublicApi;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_look_up_order_by_number(): void
    {
        $order = Order::factory()->create(['order_number' => 'TB-ABC123']);
        $order->items()->create([
            'item_type' => 'meal',
            'name' => 'Butter Chicken Tiffin',
            'unit_price' => 18.50,
            'quantity' => 1,
        ]);

        $this->getJson('/api/v1/orders/TB-ABC123')
            ->assertOk()
            ->assertJsonPath('data.order_number', 'TB-ABC123')
            ->assertJsonPath('data.items.0.quantity', 1);
    }

    public function test_unknown_order_number_returns_404(): void
    {
        $this->getJson('/api/v1/orders/TB-NOPE99')->assertNotFound();
    }
}
