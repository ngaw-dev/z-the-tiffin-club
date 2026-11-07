<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Mail\OrderConfirmation;
use App\Mail\OrderStatusUpdate;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderEmailsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'customer_email' => 'amrit@example.com',
        ], $attributes));

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'meal',
            'name' => 'Butter Chicken Thali',
            'unit_price' => '18.00',
            'quantity' => 2,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'extra',
            'name' => 'Garlic Naan',
            'unit_price' => '4.50',
            'quantity' => 3,
        ]);

        $order->refresh();
        $order->update(['order_total' => '49.50']);

        return $order->refresh();
    }

    public function test_order_confirmation_mailable_renders_with_items_and_total(): void
    {
        $order = $this->makeOrder(['fulfilment_type' => 'delivery']);

        $mailable = new OrderConfirmation($order);

        $mailable->assertSeeInHtml($order->order_number);
        $mailable->assertSeeInHtml('Butter Chicken Thali');
        $mailable->assertSeeInHtml('Garlic Naan');
        $mailable->assertSeeInHtml('49.50');
        $mailable->assertSeeInHtml($order->delivery_address);
        $mailable->assertSeeInHtml('Delivery');
    }

    public function test_order_confirmation_renders_pickup_window_for_pickup_orders(): void
    {
        $order = $this->makeOrder(['fulfilment_type' => 'pickup']);

        $mailable = new OrderConfirmation($order);

        $mailable->assertSeeInHtml($order->order_number);
        $mailable->assertSeeInHtml('18:00 - 20:00');
        $mailable->assertSeeInHtml('123 Cuba Street, Wellington');
        $mailable->assertSeeInHtml('Pickup');
    }

    public function test_order_status_update_mailable_renders_copy_per_status(): void
    {
        $order = $this->makeOrder(['fulfilment_type' => 'pickup']);

        $mailable = new OrderStatusUpdate($order, 'out_for_delivery');

        $mailable->assertSeeInHtml($order->order_number);
        $mailable->assertSeeInHtml('on its way');

        $mailable = new OrderStatusUpdate($order, 'ready_for_pickup');

        $mailable->assertSeeInHtml('ready for pickup');
        $mailable->assertSeeInHtml('18:00 - 20:00');
        $mailable->assertSeeInHtml('123 Cuba Street, Wellington');
    }

    public function test_confirmation_email_is_queued_and_sent_on_order_placed_event(): void
    {
        Mail::fake();

        $order = $this->makeOrder();

        event(new OrderPlaced($order));

        Mail::assertQueued(OrderConfirmation::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id
                && $mail->hasTo($order->customer_email);
        });
    }

    public function test_status_update_email_is_queued_and_sent_on_status_changed_event(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['status' => 'preparing']);

        event(new OrderStatusChanged($order));

        Mail::assertQueued(OrderStatusUpdate::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id
                && $mail->status === 'preparing'
                && $mail->hasTo($order->customer_email);
        });
    }

    public function test_both_emails_dispatched_across_order_lifecycle(): void
    {
        Mail::fake();

        $order = $this->makeOrder();

        event(new OrderPlaced($order));

        $order->update(['status' => 'completed']);
        event(new OrderStatusChanged($order->refresh()));

        Mail::assertQueued(OrderConfirmation::class, 1);
        Mail::assertQueued(OrderStatusUpdate::class, 1);
    }
}
