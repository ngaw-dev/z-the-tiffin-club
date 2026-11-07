<?php

namespace Tests\Feature\PublicApi;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmation;
use App\Models\DailyMeal;
use App\Models\Extra;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private DailyMeal $meal;

    private Extra $extra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meal = DailyMeal::factory()->create([
            'price' => 18.50,
            'max_per_order' => 5,
        ]);
        $this->extra = Extra::factory()->create([
            'daily_meal_id' => $this->meal->id,
            'price' => 4.00,
        ]);

        // Always inside the delivery/pickup windows.
        $this->travelTo(now()->setTime(12, 0));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                ['item_type' => 'meal', 'quantity' => 2],
                ['item_type' => 'extra', 'extra_id' => null, 'quantity' => 1],
            ],
            'fulfilment_type' => 'pickup',
            'customer_name' => 'Amol Tester',
            'customer_email' => 'amol@example.test',
            'customer_phone' => '0211234567',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function validItems(): array
    {
        return [
            ['item_type' => 'meal', 'quantity' => 2],
            ['item_type' => 'extra', 'extra_id' => $this->extra->id, 'quantity' => 1],
        ];
    }

    public function test_guest_can_place_a_pickup_order_with_server_computed_totals(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.order_number', 'TB-000001')
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.order_total', '41.00') // 2×18.50 + 1×4.00
            ->assertJsonPath('data.items.0.name', $this->meal->name)
            ->assertJsonPath('data.items.1.unit_price', '4.00');

        $this->assertDatabaseHas('orders', [
            'order_number' => 'TB-000001',
            'user_id' => null,
            'order_total' => 41.00,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => 1,
            'item_type' => 'meal',
            'name' => $this->meal->name,
            'unit_price' => 18.50,
            'quantity' => 2,
        ]);
    }

    public function test_signed_in_user_order_is_linked_to_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $this->payload(['items' => $this->validItems()]))
            ->assertCreated();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'TB-000001',
            'user_id' => $user->id,
        ]);
    }

    public function test_client_sent_prices_are_ignored(): void
    {
        // A hostile client tries to send its own price; it must be ignored.
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
            'order_total' => 0.01,
        ]))->assertCreated()
            ->assertJsonPath('data.order_total', '41.00');
    }

    public function test_delivery_requires_address_in_launch_city(): void
    {
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
            'fulfilment_type' => 'delivery',
            'delivery_address' => '12 Nowhere St, Auckland',
        ]))->assertUnprocessable()->assertJsonValidationErrors('delivery_address');

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
            'fulfilment_type' => 'delivery',
            'delivery_address' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('delivery_address');
    }

    public function test_delivery_order_in_launch_city_is_accepted(): void
    {
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
            'fulfilment_type' => 'delivery',
            'delivery_address' => '12 Cuba St, Wellington',
        ]))->assertCreated()
            ->assertJsonPath('data.delivery_address', '12 Cuba St, Wellington');
    }

    public function test_meal_cap_of_five_is_enforced(): void
    {
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [
                ['item_type' => 'meal', 'quantity' => 3],
                ['item_type' => 'meal', 'quantity' => 3],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_extras_are_uncapped(): void
    {
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [
                ['item_type' => 'meal', 'quantity' => 1],
                ['item_type' => 'extra', 'extra_id' => $this->extra->id, 'quantity' => 20],
            ],
        ]))->assertCreated()->assertJsonPath('data.order_total', '98.50');
    }

    public function test_rejects_delivery_after_cutoff(): void
    {
        $this->travelTo(now()->setTime(16, 30));

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
            'fulfilment_type' => 'delivery',
            'delivery_address' => '12 Cuba St, Wellington',
        ]))->assertUnprocessable()->assertJsonValidationErrors('fulfilment_type');

        // Pickup is still open at 16:30.
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
        ]))->assertCreated();
    }

    public function test_rejects_pickup_after_cutoff(): void
    {
        $this->travelTo(now()->setTime(20, 0));

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('fulfilment_type');
    }

    public function test_rejects_when_manual_override_closes_ordering(): void
    {
        Setting::set('ordering_open', '0');

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('fulfilment_type');
    }

    public function test_rejects_when_no_meal_published_today(): void
    {
        $this->meal->delete();

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_unknown_extra_id_is_rejected(): void
    {
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [
                ['item_type' => 'extra', 'extra_id' => 99999, 'quantity' => 1],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.extra_id');
    }

    public function test_rate_limits_after_five_orders_per_minute(): void
    {
        RateLimiter::clear('place-order:127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/orders', $this->payload([
                'items' => [['item_type' => 'meal', 'quantity' => 1]],
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
        ]))->assertStatus(429);

        RateLimiter::clear('place-order:127.0.0.1');
    }

    public function test_order_placed_event_dispatches_when_guest_places_an_order(): void
    {
        Event::fake([OrderPlaced::class]);

        $response = $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
        ]));

        $response->assertCreated();

        $order = Order::query()->sole();

        Event::assertDispatched(OrderPlaced::class, function (OrderPlaced $event) use ($order) {
            return $event->order->id === $order->id
                && $event->order->items->count() === 2;
        });
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_confirmation_mailable_is_queued_when_guest_places_an_order(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/orders', $this->payload([
            'items' => $this->validItems(),
            'customer_email' => 'guest@example.test',
        ]));

        $response->assertCreated();

        $order = Order::query()->sole();

        // The listener is queued; Mail::fake records the intercepted send
        // on both sent and queued collections (same as OrderEmailsTest).
        Mail::assertQueued(OrderConfirmation::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id
                && $mail->hasTo('guest@example.test');
        });
        Mail::assertQueued(OrderConfirmation::class, 1);
    }

    public function test_no_event_dispatched_when_validation_fails(): void
    {
        Event::fake([OrderPlaced::class]);

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [['item_type' => 'meal', 'quantity' => 6]], // over cap
        ]))->assertStatus(422);

        Event::assertNotDispatched(OrderPlaced::class);
    }

    public function test_order_numbers_are_sequential_and_unique(): void
    {
        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
        ]))->assertCreated()->assertJsonPath('data.order_number', 'TB-000001');

        $this->postJson('/api/v1/orders', $this->payload([
            'items' => [['item_type' => 'meal', 'quantity' => 1]],
        ]))->assertCreated()->assertJsonPath('data.order_number', 'TB-000002');
    }
}
