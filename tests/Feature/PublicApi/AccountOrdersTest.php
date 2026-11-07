<?php

namespace Tests\Feature\PublicApi;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/account/orders')->assertUnauthorized();
    }

    public function test_returns_only_the_authenticated_users_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Order::factory()->forUser($user)->create(['order_number' => 'TB-100001']);
        $mine->items()->create([
            'item_type' => 'meal',
            'name' => 'Butter Chicken Tiffin',
            'unit_price' => 18.50,
            'quantity' => 2,
        ]);
        Order::factory()->forUser($other)->create(['order_number' => 'TB-100002']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/account/orders');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', 'TB-100001')
            ->assertJsonPath('data.0.items.0.name', 'Butter Chicken Tiffin');
    }
}
