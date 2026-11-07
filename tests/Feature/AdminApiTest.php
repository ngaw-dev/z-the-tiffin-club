<?php

namespace Tests\Feature;

use App\Events\OrderStatusChanged;
use App\Models\DailyMeal;
use App\Models\Extra;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use Database\Factories\OwnerUserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = OwnerUserFactory::new()->create();
        $this->customer = User::factory()->create(); // default role: customer
        Storage::fake('public');
    }

    private function ownerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->owner->createToken('test')->plainTextToken];
    }

    private function customerHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->customer->createToken('test')->plainTextToken];
    }

    // ---- Access control (T4 acceptance) ----

    public function test_guest_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertStatus(401);
    }

    public function test_customer_is_blocked_from_admin_routes(): void
    {
        $this->getJson('/api/v1/admin/settings', $this->customerHeaders())->assertStatus(403);
        $this->getJson('/api/v1/admin/orders', $this->customerHeaders())->assertStatus(403);
        $this->postJson('/api/v1/admin/meals', [], $this->customerHeaders())->assertStatus(403);
    }

    public function test_owner_can_access_admin_routes(): void
    {
        $this->getJson('/api/v1/admin/settings', $this->ownerHeaders())->assertStatus(200);
    }

    // ---- T4.1 Meals ----

    public function test_owner_publishes_today_meal_with_photo_variants(): void
    {
        $response = $this->postJson('/api/v1/admin/meals', [
            'name' => 'Paneer Tikka Tiffin',
            'description' => 'Grilled paneer, basmati rice, raita.',
            'price' => 18.50,
            'max_per_order' => 4,
            'photo' => UploadedFile::fake()->image('meal.jpg', 1600, 1200),
        ], $this->ownerHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Paneer Tikka Tiffin')
            ->assertJsonPath('data.price', '18.50');

        $meal = DailyMeal::query()->forToday()->first();
        $this->assertNotNull($meal);
        $this->assertTrue(str_ends_with($meal->photo_path, '-full.jpg'));
        Storage::disk('public')->assertExists($meal->photo_path);
        Storage::disk('public')->assertExists(preg_replace('/-full\.jpg$/', '-thumb.jpg', $meal->photo_path));
    }

    public function test_publishing_a_meal_twice_for_the_same_date_conflicts(): void
    {
        DailyMeal::factory()->create();

        $this->postJson('/api/v1/admin/meals', [
            'name' => 'Second Meal', 'description' => 'd', 'price' => 10,
        ], $this->ownerHeaders())->assertStatus(409);
    }

    public function test_owner_amends_a_meal(): void
    {
        $meal = DailyMeal::factory()->create(['price' => 15]);

        $this->putJson('/api/v1/admin/meals/'.$meal->menu_date->toDateString(), [
            'price' => 17.25,
        ], $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.price', '17.25');
    }

    public function test_owner_cannot_unpublish_meal_with_orders(): void
    {
        $meal = DailyMeal::factory()->create();
        $order = Order::factory()->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'meal',
            'daily_meal_id' => $meal->id,
            'name' => $meal->name,
            'unit_price' => $meal->price,
            'quantity' => 1,
        ]);

        $this->deleteJson('/api/v1/admin/meals/'.$meal->menu_date->toDateString(), [], $this->ownerHeaders())
            ->assertStatus(409);
        $this->assertDatabaseCount('daily_meals', 1);
    }

    public function test_owner_unpublishes_meal_without_orders(): void
    {
        $meal = DailyMeal::factory()->create();
        Extra::factory()->count(2)->create(['daily_meal_id' => $meal->id]);

        $this->deleteJson('/api/v1/admin/meals/'.$meal->menu_date->toDateString(), [], $this->ownerHeaders())
            ->assertStatus(200);
        $this->assertDatabaseCount('daily_meals', 0);
        $this->assertDatabaseCount('extras', 0);
    }

    // ---- T4.1 Extras CRUD ----

    public function test_extras_crud_on_today_meal(): void
    {
        DailyMeal::factory()->create();

        $create = $this->postJson('/api/v1/admin/meals/today/extras', [
            'name' => 'Mango Lassi', 'price' => 5.50, 'description' => 'Fresh mango yoghurt drink.',
        ], $this->ownerHeaders());
        $create->assertStatus(201)->assertJsonPath('data.name', 'Mango Lassi');

        $extraId = $create->json('data.id');

        $this->getJson('/api/v1/admin/meals/today/extras', $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/admin/extras/{$extraId}", [
            'name' => 'Mango Lassi', 'price' => 6.00,
        ], $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.price', '6.00');

        $this->deleteJson("/api/v1/admin/extras/{$extraId}", [], $this->ownerHeaders())
            ->assertStatus(200);
        $this->assertDatabaseCount('extras', 0);
    }

    public function test_extras_require_a_published_meal(): void
    {
        $this->getJson('/api/v1/admin/meals/today/extras', $this->ownerHeaders())->assertStatus(404);
        $this->postJson('/api/v1/admin/meals/today/extras', [
            'name' => 'Samosa', 'price' => 3,
        ], $this->ownerHeaders())->assertStatus(404);
    }

    public function test_extra_referenced_by_orders_cannot_be_deleted(): void
    {
        $meal = DailyMeal::factory()->create();
        $extra = Extra::factory()->create(['daily_meal_id' => $meal->id]);
        $order = Order::factory()->create();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'item_type' => 'extra',
            'extra_id' => $extra->id,
            'name' => $extra->name,
            'unit_price' => $extra->price,
            'quantity' => 2,
        ]);

        $this->deleteJson('/api/v1/admin/extras/'.$extra->id, [], $this->ownerHeaders())->assertStatus(409);
    }

    // ---- T4.2 Settings ----

    public function test_owner_reads_default_settings(): void
    {
        $this->getJson('/api/v1/admin/settings', $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.delivery_cutoff', '16:00')
            ->assertJsonPath('data.ordering_open', '1');
    }

    public function test_owner_updates_settings(): void
    {
        $this->putJson('/api/v1/admin/settings', [
            'delivery_cutoff' => '15:30',
            'ordering_open' => false,
            'delivery_city' => 'Auckland',
        ], $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.delivery_cutoff', '15:30')
            ->assertJsonPath('data.ordering_open', '0')
            ->assertJsonPath('data.delivery_city', 'Auckland');

        $this->assertSame('0', Setting::get('ordering_open'));
    }

    public function test_settings_validation_rejects_bad_time(): void
    {
        $this->putJson('/api/v1/admin/settings', [
            'delivery_cutoff' => '25:99',
        ], $this->ownerHeaders())->assertStatus(422);
    }

    // ---- T4.3 Orders ----

    public function test_orders_index_filters_and_paginates(): void
    {
        $delivery = Order::factory()->create(['fulfilment_type' => 'delivery', 'status' => 'received', 'ordered_at' => today()->setHour(12)]);
        Order::factory()->create(['fulfilment_type' => 'pickup', 'status' => 'completed', 'ordered_at' => today()->setHour(13)]);
        Order::factory()->create(['fulfilment_type' => 'delivery', 'ordered_at' => now()->subDay()]);

        $this->getJson('/api/v1/admin/orders?date='.today()->toDateString(), $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/orders?fulfilment=delivery', $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_number', $delivery->order_number);

        $this->getJson('/api/v1/admin/orders?status=completed', $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_order_status_advances_through_flow(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $order = Order::factory()->create(['status' => 'received', 'fulfilment_type' => 'delivery']);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'preparing'], $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', 'preparing');

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'out_for_delivery'], $this->ownerHeaders())
            ->assertStatus(200);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'completed'], $this->ownerHeaders())
            ->assertStatus(200);

        Event::assertDispatchedTimes(OrderStatusChanged::class, 3);
    }

    public function test_order_status_flow_rejects_invalid_transitions(): void
    {
        $order = Order::factory()->create(['status' => 'received', 'fulfilment_type' => 'delivery']);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'completed'], $this->ownerHeaders())
            ->assertStatus(422);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'ready_for_pickup'], $this->ownerHeaders())
            ->assertStatus(422);
    }

    public function test_order_can_be_cancelled_from_preparing(): void
    {
        $order = Order::factory()->create(['status' => 'preparing']);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'], $this->ownerHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');
    }
}
